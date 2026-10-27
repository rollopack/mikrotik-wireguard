<?php

require_once __DIR__ . '/ClientInterface.php';

class MikrotikRestClient implements ClientInterface {
    /** Fields the dashboard needs from every peer (RouterOS `.proplist`). */
    private const PEER_FIELDS = [
        '.id', 'name', 'interface', 'allowed-address', 'last-handshake',
        'current-endpoint-address', 'public-key', 'disabled', 'rx', 'tx',
    ];

    /** Fields needed for IP allocation across interfaces. */
    private const ALLOCATION_FIELDS = ['.id', 'name', 'allowed-address', 'interface', 'public-key'];

    private string $baseUrl;
    private string $username;
    private string $password;
    private bool $sslVerify;
    private int $timeout;
    private string $interface = 'WireGuard-ResNovae';

    /**
     * MikrotikRestClient constructor.
     * 
     * @param string $host Hostname, IP address or full URL of the MikroTik CHR.
     * @param string $username
     * @param string $password
     * @param bool $sslVerify Whether to verify SSL certificates (default false).
     * @param int $timeout Connection timeout in seconds.
     */
    public function __construct(
        string $host,
        string $username,
        string $password,
        bool $sslVerify = false,
        int $timeout = 10,
        string $interface = 'WireGuard-ResNovae'
    ) {
        // Build base URL. If protocol is not specified, default to https://
        if (!str_starts_with($host, 'http://') && !str_starts_with($host, 'https://')) {
            $host = 'https://' . $host;
        }
        
        // Ensure no trailing slash
        $this->baseUrl = rtrim($host, '/');
        if (!str_ends_with($this->baseUrl, '/rest')) {
            $this->baseUrl .= '/rest';
        }

        $this->username = $username;
        $this->password = $password;
        $this->sslVerify = $sslVerify;
        $this->timeout = $timeout;
        $this->interface = $interface;
    }

    /**
     * Send an HTTP request to the RouterOS REST API.
     * 
     * @param string $method HTTP method (GET, POST, PATCH, PUT, DELETE)
     * @param string $path API endpoint path (e.g., '/interface/wireguard/peers')
     * @param array|null $data Payload data to send in the body.
     * @param array $query Query string parameters (e.g. ['.proplist' => '.id,name']).
     * @return array Decoded JSON response.
     * @throws Exception on connection failure or API error response.
     */
    public function request(string $method, string $path, ?array $data = null, array $query = []): array {
        $url = $this->buildUrl($path, $query);
        $method = strtoupper($method);
        
        $headers = [
            'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password),
            'Accept: application/json',
        ];
        
        $content = '';
        if ($data !== null) {
            $jsonData = json_encode($data);
            if ($jsonData === false) {
                throw new Exception("Failed to encode JSON payload: " . json_last_error_msg());
            }
            $content = $jsonData;
            $headers[] = 'Content-Type: application/json';
        }
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $content,
                'ignore_errors' => true, // Don't throw PHP warnings on 4xx/5xx status codes
                'timeout' => $this->timeout,
            ],
            'ssl' => [
                'verify_peer' => $this->sslVerify,
                'verify_peer_name' => $this->sslVerify,
            ]
        ];
        
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            throw new Exception("Network request failed: " . ($error['message'] ?? 'Unknown connection error') . " - Target: $url");
        }
        
        $httpCode = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $http_response_header[0], $matches)) {
                $httpCode = (int)$matches[1];
            }
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMessage = "API returned HTTP $httpCode";
            if (is_array($decoded)) {
                if (isset($decoded['detail'])) {
                    $errorMessage .= ': ' . $decoded['detail'];
                } elseif (isset($decoded['message'])) {
                    $errorMessage .= ': ' . $decoded['message'];
                } elseif (isset($decoded['error']) && is_string($decoded['error'])) {
                    $errorMessage .= ': ' . $decoded['error'];
                }
            } else {
                $errorMessage .= ': ' . substr($response, 0, 200);
            }
            throw new Exception($errorMessage);
        }
        
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Build the request URL, appending query string parameters when given.
     *
     * RouterOS REST reads query parameters as `print` arguments/filters, so
     * `.proplist` avoids transferring every peer field. Parameter names are
     * kept literal (they are ASCII identifiers such as `.proplist`) while
     * values are percent-encoded (`.id,name` becomes `.id%2Cname`).
     */
    protected function buildUrl(string $path, array $query = []): string {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        if ($query === []) {
            return $url;
        }

        $pairs = [];
        foreach ($query as $key => $value) {
            $pairs[] = $key . '=' . rawurlencode((string)$value);
        }

        return $url . '?' . implode('&', $pairs);
    }

    /**
     * Fetch the peer table asking only for the given fields.
     *
     * A RouterOS build that does not understand a query parameter treats it as
     * a filter and answers with an empty list, which would look like an empty
     * peer table. Retry once without parameters in that case.
     */
    private function requestPeerList(array $fields, array $filters = []): array {
        $query = array_merge(['.proplist' => implode(',', $fields)], $filters);
        $peers = $this->request('GET', '/interface/wireguard/peers', null, $query);

        if ($peers === []) {
            error_log('RouterOS REST: peer query returned no peers, retrying without query parameters');
            $peers = $this->request('GET', '/interface/wireguard/peers');
        }

        return $peers;
    }

    /**
     * Get all WireGuard peers with full data including last-handshake.
     * Filters by configured interface and formats output for frontend.
     *
     * @return array List of peers with normalized fields.
     * @throws Exception on failure.
     */
    public function getPeers(): array
    {
        $peers = $this->requestPeerList(self::PEER_FIELDS, ['interface' => $this->getInterface()]);
        $interface = $this->getInterface();

        // Only extract fields needed by the frontend to reduce payload size
        $allowedFields = ['.id', 'name', 'allowed-address', 'last-handshake',
                          'current-endpoint-address', 'public-key', 'disabled'];

        $filteredPeers = [];
        foreach ($peers as $peer) {
            if (($peer['interface'] ?? '') !== $interface) {
                continue;
            }

            $out = [];
            foreach ($allowedFields as $f) {
                if (isset($peer[$f])) {
                    $out[$f] = $peer[$f];
                }
            }
            $out['disabled'] = WireGuardManager::normalizeBool($peer['disabled'] ?? false);
            $out['rx_formatted'] = WireGuardManager::formatBytes($peer['rx'] ?? 0);
            $out['tx_formatted'] = WireGuardManager::formatBytes($peer['tx'] ?? 0);
            $out['handshake_formatted'] = WireGuardManager::formatHandshake($peer['last-handshake'] ?? '');

            $filteredPeers[] = $out;
        }

        return array_values($filteredPeers);
    }

    /**
     * Get ALL WireGuard peers without interface filtering.
     * Used for IP allocation to avoid collisions across interfaces.
     *
     * @return array List of all peers with normalized fields.
     * @throws Exception on failure.
     */
    public function getAllPeers(): array
    {
        $peers = $this->requestPeerList(self::ALLOCATION_FIELDS);

        $allowedFields = self::ALLOCATION_FIELDS;
        
        $result = [];
        foreach ($peers as $peer) {
            $out = [];
            foreach ($allowedFields as $f) {
                if (isset($peer[$f])) {
                    $out[$f] = $peer[$f];
                }
            }
            $result[] = $out;
        }
        
        return $result;
    }

    /**
     * Get the server's WireGuard public key.
     *
     * @return string Public key in base64.
     * @throws Exception on failure.
     */
    public function getServerPublicKey(): string
    {
        $interfaces = $this->request('GET', '/interface/wireguard');
        foreach ($interfaces as $iface) {
            if (($iface['name'] ?? '') === $this->getInterface()) {
                return $iface['public-key'] ?? '';
            }
        }
        throw new Exception("WireGuard interface '" . $this->getInterface() . "' not found on the MikroTik CHR.");
    }

    /**
     * Get the WireGuard interface name from config.
     *
     * @return string
     */
    public function getInterface(): string
    {
        return $this->interface ?? 'WireGuard-ResNovae';
    }

    /**
     * Get the peer list and the interface status in one dashboard payload.
     *
     * REST has no combined endpoint, so this still costs two GETs; the second
     * one answers in tens of milliseconds because it returns a single object.
     *
     * @return array{peers: array, interface: array}
     * @throws Exception on failure.
     */
    public function getPeersAndInterface(): array
    {
        return [
            'peers' => $this->getPeers(),
            'interface' => $this->getInterfaceStatus(),
        ];
    }

    /**
     * Get the status of the WireGuard interface.
     *
     * @return array{name: string, running: bool, disabled: bool, 'listen-port': int, mtu: int, 'public-key': string, comment: string}
     * @throws Exception on failure.
     */
    public function getInterfaceStatus(): array
    {
        $interfaces = $this->request('GET', '/interface/wireguard');
        foreach ($interfaces as $iface) {
            if (($iface['name'] ?? '') === $this->getInterface()) {
                return [
                    'name' => $iface['name'] ?? '',
                    'running' => WireGuardManager::normalizeBool($iface['running'] ?? 'false'),
                    'disabled' => WireGuardManager::normalizeBool($iface['disabled'] ?? 'false'),
                    'listen-port' => (int)($iface['listen-port'] ?? 0),
                    'mtu' => (int)($iface['mtu'] ?? 0),
                    'public-key' => $iface['public-key'] ?? '',
                    'comment' => $iface['comment'] ?? '',
                ];
            }
        }
        throw new Exception("WireGuard interface '" . $this->getInterface() . "' not found on the MikroTik CHR.");
    }

    /**
     * Add a new WireGuard peer.
     *
     * @param array $payload Peer data (interface, public-key, allowed-address, name)
     * @return array Created peer data including .id
     * @throws Exception on failure.
     */
    public function addPeer(array $payload): array
    {
        return $this->request('PUT', '/interface/wireguard/peers', $payload);
    }

    /**
     * Update an existing WireGuard peer.
     *
     * @param string $id Peer ID (e.g., *1c)
     * @param array $payload Update data (name, public-key, etc.)
     * @return void
     * @throws Exception on failure.
     */
    public function updatePeer(string $id, array $payload): void
    {
        $this->request('PATCH', '/interface/wireguard/peers/' . $id, $payload);
    }

    /**
     * Delete a WireGuard peer.
     *
     * @param string $id Peer ID (e.g., *1c)
     * @return void
     * @throws Exception on failure.
     */
    public function deletePeer(string $id): void
    {
        $this->request('DELETE', '/interface/wireguard/peers/' . $id);
    }

    /**
     * Get PPP secrets (for SSTP/PPTP export).
     *
     * @return array List of PPP secrets
     * @throws Exception on failure.
     */
    public function getPppSecrets(): array
    {
        return $this->request('GET', '/ppp/secret');
    }

    /**
     * Get PPP active connections.
     *
     * @return array List of active PPP connections
     * @throws Exception on failure.
     */
    public function getPppActive(): array
    {
        return $this->request('GET', '/ppp/active');
    }
}

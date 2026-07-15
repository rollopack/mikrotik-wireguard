<?php

require_once __DIR__ . '/ClientInterface.php';

class MikrotikRestClient implements ClientInterface {
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
     * @return array Decoded JSON response.
     * @throws Exception on connection failure or API error response.
     */
    public function request(string $method, string $path, ?array $data = null): array {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
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
                }
            } else {
                $errorMessage .= ': ' . substr($response, 0, 200);
            }
            throw new Exception($errorMessage);
        }
        
        return is_array($decoded) ? $decoded : [];
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
        $peers = $this->request('GET', '/interface/wireguard/peers');
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
            $raw = $peer['disabled'] ?? false;
            $out['disabled'] = $raw === true || $raw === 'true' || $raw === 'yes';
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
        $peers = $this->request('GET', '/interface/wireguard/peers');
        
        $allowedFields = ['.id', 'name', 'allowed-address', 'interface', 'public-key'];
        
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
                    'running' => ($iface['running'] ?? 'false') === 'true',
                    'disabled' => ($iface['disabled'] ?? 'false') === 'true',
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
     * Set the WireGuard interface name.
     *
     * @param string $interface
     * @return void
     */
    public function setInterface(string $interface): void
    {
        $this->interface = $interface;
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

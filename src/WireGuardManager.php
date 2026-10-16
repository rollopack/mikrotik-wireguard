<?php

require_once __DIR__ . '/ClientInterface.php';

class WireGuardManager {
    const SUBNET_REGEX = '/^([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)\/([0-9]+)$/';

    private ClientInterface $client;
    private array $config;

    /**
     * WireGuardManager constructor.
     * 
     * @param ClientInterface $client API client (MikrotikRestClient, MikrotikApiClient, or Mock implementing ClientInterface)
     * @param array $config Manager configurations (interface, subnet, server_ip, etc.)
     */
    public function __construct(ClientInterface $client, array $config) {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * Generate a new X25519 WireGuard key pair.
     * 
     * @return array Array containing 'private_key' and 'public_key' in base64 format.
     */
    public static function generateKeyPair(): array {
        $privateKeyBytes = random_bytes(32);
        $publicKeyBytes = sodium_crypto_scalarmult_base($privateKeyBytes);
        
        return [
            'private_key' => base64_encode($privateKeyBytes),
            'public_key' => base64_encode($publicKeyBytes)
        ];
    }

    /**
     * Calculate the maximum number of usable client IPs in a subnet.
     *
     * Excludes network address. Optionally excludes server IP if distinct from network.
     *
     * @param string $subnet CIDR notation (e.g. 3.0.0.0/21)
     * @param string|null $serverIp Server IP to exclude (null to not exclude)
     * @return int Number of usable IPs
     * @throws Exception on invalid subnet format
     */
    public static function maxPeers(string $subnet, ?string $serverIp = null): int {
        if (!preg_match(self::SUBNET_REGEX, $subnet, $m)) {
            throw new Exception("Invalid subnet format: " . $subnet);
        }
        $size = 1 << (32 - (int)$m[2]);
        $netLong = ip2long($m[1]) & ~($size - 1);
        $maxPeers = $size - 1;
        if ($serverIp !== null) {
            $srvLong = ip2long($serverIp);
            if ($srvLong !== $netLong) {
                $maxPeers--;
            }
        }
        return $maxPeers;
    }

    /**
     * Format RouterOS duration string (e.g. "20h44m42s") into readable format (e.g. "20h 44m 42s").
     * 
     * @param string $duration Raw duration from RouterOS
     * @return string Formatted duration or 'never' if empty
     */
    public static function formatHandshake(string $duration): string {
        if (empty($duration) || $duration === 'never') {
            return 'never';
        }
        // Add space between time units: 20h44m42s -> 20h 44m 42s
        return rtrim(preg_replace('/(\d+)([dhms])/', '$1$2 ', $duration));
    }

    /**
     * Normalize a mixed boolean-like value from RouterOS to a PHP bool.
     *
     * RouterOS REST API returns 'true'/'false', native API returns 'yes'/'no',
     * and PHP native API bridge may return native bools.
     *
     * For the stricter, JSON-facing variant used by the public API see
     * parseBoolStrict().
     */
    public static function normalizeBool($value): bool {
        return $value === true || $value === 'true' || $value === 'yes';
    }

    /**
     * Format a numeric or pre-formatted bytes value into a human-readable format.
     * 
     * @param mixed $bytes
     * @return string
     */
    public static function formatBytes($bytes): string {
        if (is_numeric($bytes)) {
            $bytes = (float) $bytes;
            $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
            $pow = 0;
            if ($bytes > 0) {
                $pow = min(floor(log($bytes, 1024)), count($units) - 1);
                $bytes /= pow(1024, $pow);
            }
            return number_format($bytes, 2, '.', '') . ' ' . $units[$pow];
        }
        
        if (is_string($bytes)) {
            // If already formatted like "5.7MiB" or "480.3KiB"
            return preg_replace('/([0-9.]+)([a-zA-Z]+)/', '$1 $2', self::translateUnits($bytes));
        }
        
        return '0 B';
    }

    /**
     * Translate MikroTik units to standard units (e.g. MiB, KiB).
     */
    private static function translateUnits(string $str): string {
        return str_replace(['bps', 'kbps', 'mbps', 'gbps'], [' B/s', ' KiB/s', ' MiB/s', ' GiB/s'], $str);
    }

    /**
     * Parse allowed-address lists and find the next free IP address in the subnet.
     * 
     * @param array $peers List of peers returned from MikroTik.
     * @return string Next free IP address.
     * @throws Exception if subnet cannot be parsed or subnet is full.
     */
    public function calculateNextFreeIp(array $peers): string {
        $subnet = $this->config['subnet'] ?? '3.0.0.0/21';

        if (!preg_match(self::SUBNET_REGEX, $subnet, $matches)) {
            throw new Exception("Invalid subnet format: " . $subnet);
        }

        $subnetLong = ip2long($matches[1]);
        $mask = (int)$matches[2];
        $size = 1 << (32 - $mask);
        $networkStart = $subnetLong & ~($size - 1);
        $networkEnd = $networkStart + $size - 1;
        $serverLong = ip2long($this->config['server_ip'] ?? '3.0.0.1');

        $allocated = [];
        foreach ($peers as $peer) {
            foreach (explode(',', $peer['allowed-address'] ?? '') as $addr) {
                if (preg_match('/^([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/', trim($addr), $m)) {
                    $allocated[$m[1]] = true;
                }
            }
        }

        for ($candidate = $networkStart + 1; $candidate <= $networkEnd; $candidate++) {
            if ($candidate === $serverLong) continue;
            $ip = long2ip($candidate);
            if (!isset($allocated[$ip])) return $ip;
        }

        throw new Exception("No free IP addresses left in subnet " . $subnet);
    }

    /**
     * Get the server's public key from the configured wireguard interface.
     * 
     * @return string
     * @throws Exception
     */
    public function getServerPublicKey(): string {
        return $this->client->getServerPublicKey();
    }

    /**
     * Get list of peers with formatted fields.
     * 
     * @return array
     * @throws Exception on API error
     */
    public function getPeers(): array {
        return $this->client->getPeers();
    }

    /**
     * Add a new peer.
     * 
     * @param string $name Comment/Name for the new peer.
     * @return array Array containing client config details.
     * @throws Exception on API error or subnet full
     */
    public function addPeer(string $name): array {
        $allPeers = $this->client->getAllPeers();

        $interface = $this->config['interface'] ?? '';
        foreach ($allPeers as $p) {
            if (($p['interface'] ?? '') === $interface && strcasecmp(($p['name'] ?? ''), $name) === 0) {
                throw new Exception("A peer with name '" . $name . "' already exists.");
            }
        }

        $clientIp = $this->calculateNextFreeIp($allPeers);
        $clientKeys = self::generateKeyPair();
        $serverPublicKey = $this->getServerPublicKey();

        $payload = [
            'interface' => $this->config['interface'] ?? '',
            'public-key' => $clientKeys['public_key'],
            'allowed-address' => $clientIp . '/32',
            'name' => $name,
        ];

        if ($this->config['client_export_metadata'] ?? true) {
            // These fields enable RouterOS "show-client-config" / QR export.
            // Older CHRs can disable them because the properties are unavailable.
            $clientEndpoint = trim((string)($this->config['endpoint'] ?? ''));
            if ($clientEndpoint !== '') {
                $payload['client-endpoint'] = self::extractEndpointHost($clientEndpoint);
            }

            $clientDns = self::normalizeClientDns($this->config['client_dns'] ?? '');
            if ($clientDns !== '') {
                $payload['client-dns'] = $clientDns;
            }
            $payload['client-address'] = $clientIp . '/32';
        }

        $result = $this->client->addPeer($payload);
        $newPeerId = $result['.id'] ?? $this->resolvePeerId($clientKeys, $name);

        $this->handleCollision($clientIp, $newPeerId);

        $comment = $this->config['comment'] ?? ($this->config['interface'] ?? '');

        return [
            '.id' => $newPeerId,
            'name' => $name,
            'ip' => $clientIp,
            'public_key' => $clientKeys['public_key'],
            'private_key' => $clientKeys['private_key'],
            'config' => self::generateConfig(
                $clientIp,
                $clientKeys['private_key'],
                $serverPublicKey,
                $this->config['endpoint'] ?? '',
                $this->config['client_allowed_ips'] ?? '',
                $this->config['client_dns'] ?? ''
            ),
            'script' => self::generateRscScript(
                $clientIp,
                $clientKeys['private_key'],
                $serverPublicKey,
                $this->config['endpoint'] ?? '',
                $this->config['client_allowed_ips'] ?? '',
                $this->config['interface'] ?? 'wg-resnovae',
                $comment,
                $this->config['server_ip'] ?? '3.0.0.1',
                $this->config['subnet'] ?? '3.0.0.0/21',
                $this->config['client_dns'] ?? ''
            ),
        ];
    }

    private function resolvePeerId(array $clientKeys, string $name): ?string {
        $updatedPeers = $this->getPeers();
        foreach ($updatedPeers as $p) {
            if (($p['public-key'] ?? '') === $clientKeys['public_key']) {
                return $p['.id'] ?? null;
            }
        }
        foreach ($updatedPeers as $p) {
            if (($p['name'] ?? '') === $name) {
                return $p['.id'] ?? null;
            }
        }
        return null;
    }

    private function handleCollision(string &$clientIp, ?string $newPeerId): void {
        $peerIp = $clientIp . '/32';
        $maxRetries = 3;
        for ($retry = 0; $retry < $maxRetries; $retry++) {
            $collision = false;
            $allPeersNow = $this->client->getAllPeers();
            foreach ($allPeersNow as $p) {
                if ($newPeerId !== null && ($p['.id'] ?? '') !== $newPeerId && ($p['allowed-address'] ?? '') === $peerIp) {
                    $collision = true;
                    break;
                }
            }
            if (!$collision) break;
            $clientIp = $this->calculateNextFreeIp($allPeersNow);
            $this->client->updatePeer($newPeerId, ['allowed-address' => $clientIp . '/32']);
            $peerIp = $clientIp . '/32';
        }
    }

    /**
     * Update an existing peer name/comment.
     * 
     * @param string $id MikroTik rest ID (e.g. *1c).
     * @param string $newName
     */
    public function updatePeer(string $id, string $newName): void {
        $payload = [
            'name' => $newName,
        ];
        $this->client->updatePeer($id, $payload);
    }

    /**
     * Parse a strictly boolean value into a PHP bool.
     *
     * Accepts true/false and the explicit string forms "1"/"0", "true"/"false",
     * "on"/"off". Any other value returns null.
     *
     * Unlike normalizeBool(), which accepts the RouterOS 'yes'/'no' values,
     * this is the stricter, JSON-facing variant used by the public API.
     *
     * @param mixed $value
     * @return bool|null
     */
    public static function parseBoolStrict($value): ?bool {
        if (is_bool($value)) {
            return $value;
        }
        if (!is_scalar($value)) {
            return null;
        }
        $mapped = [
            '1' => true, 'true' => true, 'on' => true,
            '0' => false, 'false' => false, 'off' => false,
        ];
        return $mapped[strtolower((string)$value)] ?? null;
    }

    /**
     * Regenerate key pair for an existing peer and update public-key on the CHR.
     * 
     * @param string $id MikroTik rest ID (e.g. *1c).
     * @return array Array containing 'public_key' and 'private_key'.
     */
    public function regenerateKey(string $id): array {
        $clientKeys = self::generateKeyPair();

        $payload = [
            'public-key' => $clientKeys['public_key'],
        ];

        $this->client->updatePeer($id, $payload);

        return $clientKeys;
    }

    /**
     * Find a peer by its name (case-insensitive).
     * 
     * @param string $name Peer name/comment.
     * @return array|null Peer data as returned by getPeers(), or null if not found.
     */
    public function findPeerByName(string $name): ?array {
        $peers = $this->getPeers();
        foreach ($peers as $p) {
            if (strcasecmp(($p['name'] ?? ''), $name) === 0) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Regenerate the key pair of an existing peer looked up by name.
     * The peer keeps its name and IP; only the key pair is replaced.
     * 
     * @param string $name Peer name/comment.
     * @return array Same structure as addPeer(): name, ip, keys, config, script.
     * @throws Exception if the peer name is not found.
     */
    public function regeneratePeer(string $name): array {
        $peer = $this->findPeerByName($name);
        if ($peer === null) {
            throw new Exception("A peer with name '" . $name . "' was not found.");
        }

        $id = $peer['.id'] ?? null;
        if (empty($id)) {
            throw new Exception("A peer with name '" . $name . "' has no id.");
        }

        $keys = $this->regenerateKey($id);
        $peerIp = explode('/', $peer['allowed-address'] ?? '')[0];
        $serverPublicKey = $this->getServerPublicKey();
        $comment = $this->config['comment'] ?? ($this->config['interface'] ?? '');
        $interfaceName = $this->config['interface'] ?? 'wg-resnovae';

        return [
            '.id' => $id,
            'name' => $peer['name'] ?? $name,
            'ip' => $peerIp,
            'public_key' => $keys['public_key'],
            'private_key' => $keys['private_key'],
            'config' => self::generateConfig(
                $peerIp,
                $keys['private_key'],
                $serverPublicKey,
                $this->config['endpoint'] ?? '',
                $this->config['client_allowed_ips'] ?? '',
                $this->config['client_dns'] ?? ''
            ),
            'script' => self::generateRscScript(
                $peerIp,
                $keys['private_key'],
                $serverPublicKey,
                $this->config['endpoint'] ?? '',
                $this->config['client_allowed_ips'] ?? '',
                $interfaceName,
                $comment,
                $this->config['server_ip'] ?? '3.0.0.1',
                $this->config['subnet'] ?? '3.0.0.0/21',
                $this->config['client_dns'] ?? ''
            ),
        ];
    }

    /**
     * Delete an existing peer.
     * 
     * @param string $id MikroTik rest ID (e.g. *1c).
     */
    public function deletePeer(string $id): void {
        $this->client->deletePeer($id);
    }

    /**
     * Delete a peer looked up by name.
     * 
     * @param string $name Peer name/comment.
     * @return array Deleted peer reference: name and ip.
     * @throws Exception if the peer name is not found.
     */
    public function deletePeerByName(string $name): array {
        $peer = $this->findPeerByName($name);
        if ($peer === null) {
            throw new Exception("A peer with name '" . $name . "' was not found.");
        }
        $id = $peer['.id'] ?? null;
        if (empty($id)) {
            throw new Exception("A peer with name '" . $name . "' has no id.");
        }
        $this->deletePeer($id);
        return [
            'name' => $peer['name'] ?? $name,
            'ip' => explode('/', $peer['allowed-address'] ?? '')[0],
        ];
    }

    /**
     * Enable or disable a peer looked up by name.
     * 
     * @param string $name Peer name/comment.
     * @param bool $disabled true to disable, false to enable.
     * @return array Peer reference: name, ip and resulting disabled state.
     * @throws Exception if the peer name is not found.
     */
    public function togglePeerByName(string $name, bool $disabled): array {
        $peer = $this->findPeerByName($name);
        if ($peer === null) {
            throw new Exception("A peer with name '" . $name . "' was not found.");
        }
        $id = $peer['.id'] ?? null;
        if (empty($id)) {
            throw new Exception("A peer with name '" . $name . "' has no id.");
        }
        $this->togglePeer($id, $disabled);
        return [
            'name' => $peer['name'] ?? $name,
            'ip' => explode('/', $peer['allowed-address'] ?? '')[0],
            'disabled' => $disabled,
        ];
    }

    /**
     * Enable or disable a peer.
     *
     * @param string $id Peer ID (e.g. *1c).
     * @param bool $disabled true to disable, false to enable.
     */
    public function togglePeer(string $id, bool $disabled): void {
        $this->client->updatePeer($id, ['disabled' => $disabled ? 'yes' : 'no']);
    }

    /**
     * Generate standard wireguard .conf configuration content.
     */
    public static function generateConfig(
        string $clientIp,
        string $clientPrivateKey,
        string $serverPublicKey,
        string $serverEndpoint,
        string $clientAllowedIps,
        string|array $clientDns = ''
    ): string {
        $normalizedDns = self::normalizeClientDns($clientDns);
        $dnsLine = $normalizedDns !== '' ? "DNS = $normalizedDns\n" : '';
        return <<<INI
[Interface]
PrivateKey = $clientPrivateKey
Address = $clientIp/32
{$dnsLine}
[Peer]
PublicKey = $serverPublicKey
Endpoint = $serverEndpoint
AllowedIPs = $clientAllowedIps
PersistentKeepalive = 25
INI;
    }

    /**
     * Extract unique IPv4 addresses from a list of peers' allowed-address fields.
     *
     * @param array $peers List of peers with 'allowed-address' field (comma-separated CIDRs)
     * @return array Sorted unique IPv4 addresses
     */
    public static function extractUniqueIpv4Addresses(array $peers): array
    {
        $ips = [];
        foreach ($peers as $peer) {
            foreach (explode(',', $peer['allowed-address'] ?? '') as $cidr) {
                $ip = explode('/', trim($cidr))[0];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ips[] = $ip;
                }
            }
        }
        $ips = array_unique($ips);
        usort($ips, fn($a, $b) => strcmp(inet_pton($a), inet_pton($b)));
        return $ips;
    }

    /**
     * Generate MikroTik client configuration script (.rsc).
     */
    public static function generateRscScript(
        string $clientIp,
        string $clientPrivateKey,
        string $serverPublicKey,
        string $serverEndpoint,
        string $clientAllowedIps,
        string $interfaceName = "wg-resnovae",
        ?string $comment = null,
        string $serverIp = '3.0.0.1',
        string $subnet = '3.0.0.0/21',
        string|array $clientDns = ''
    ): string {
        // Parse host:port while also handling bracketed IPv6 endpoints.
        $endpoint = parse_url('udp://' . trim($serverEndpoint));
        $endpointHost = $endpoint['host'] ?? '';
        $endpointPort = $endpoint['port'] ?? 13231;
        if ($endpointHost === '') {
            throw new InvalidArgumentException("Invalid WireGuard endpoint: '$serverEndpoint'");
        }
        $endpointHost = trim($endpointHost, '[]');
        $comment = $comment ?: $interfaceName;

        $subnetParts = explode('/', $subnet);
        $networkAddress = $subnetParts[0];
        $mask = $subnetParts[1] ?? '21';

        // RouterOS DNS configuration is global, not a WireGuard peer property.
        $normalizedDns = self::normalizeClientDns($clientDns);
        $dnsLine = $normalizedDns !== '' ? "/ip dns set servers=\"$normalizedDns\"\n" : '';

        return <<<RSC
# --- MikroTik Client Setup Script ---
# paste this code into your MikroTik Terminal

/interface wireguard
add name="$interfaceName" private-key="$clientPrivateKey" mtu=1420

/interface wireguard peers
add interface="$interfaceName" public-key="$serverPublicKey" \\
    endpoint-address="$endpointHost" endpoint-port=$endpointPort \\
    allowed-address="$clientAllowedIps" persistent-keepalive=25s \\
    comment="$comment"

/ip address
add address="$clientIp/$mask" network="$networkAddress" interface="$interfaceName"
$dnsLine
/ip firewall address-list
add address=$serverIp list=MANAGEMENT
RSC;
    }

    /**
     * Normalize a configured DNS value for RouterOS and WireGuard exports.
     *
     * @param string|array $clientDns Comma-separated string or list of DNS servers.
     */
    private static function normalizeClientDns(string|array $clientDns): string
    {
        $values = is_array($clientDns) ? $clientDns : explode(',', $clientDns);
        $values = array_map(static fn($dns): string => trim((string)$dns), $values);
        $values = array_values(array_filter($values, static fn(string $dns): bool => $dns !== ''));

        return implode(', ', $values);
    }

    /**
     * RouterOS appends the WireGuard listen port when generating client config
     * from client-endpoint, so store only the host in that metadata field.
     */
    private static function extractEndpointHost(string $endpoint): string
    {
        $parsed = parse_url('udp://' . trim($endpoint));
        $host = $parsed['host'] ?? '';
        if ($host === '') {
            throw new InvalidArgumentException("Invalid WireGuard endpoint: '$endpoint'");
        }

        return trim($host, '[]');
    }

    /**
     * Extract the port from a WireGuard endpoint ("host:port").
     *
     * Returns null when the endpoint has no port or is invalid, so callers can
     * decide how to fall back (RouterOS derives it from the listen port).
     */
    public static function extractEndpointPort(string $endpoint): ?int
    {
        $parsed = parse_url('udp://' . trim($endpoint));
        if ($parsed === false || !isset($parsed['port'])) {
            return null;
        }
        $port = (int)$parsed['port'];
        return ($port >= 1 && $port <= 65535) ? $port : null;
    }
}

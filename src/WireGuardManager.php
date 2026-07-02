<?php

require_once __DIR__ . '/ClientInterface.php';

class WireGuardManager {
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
        if (!preg_match('/^([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)\/([0-9]+)$/', $subnet, $m)) {
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
        return preg_replace('/(\d+)([dhms])/', '$1$2 ', $duration);
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

        if (!preg_match('/^([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)\/([0-9]+)$/', $subnet, $matches)) {
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
        // 1. Get ALL peers (unfiltered by interface) to avoid IP collisions
        //    with peers that may have lost their interface reference
        $allPeers = $this->client->request('GET', '/interface/wireguard/peers');
        $clientIp = $this->calculateNextFreeIp($allPeers);

        // 2. Generate client keys
        $clientKeys = self::generateKeyPair();

        // 3. Get server public key
        $serverPublicKey = $this->getServerPublicKey();

        // 4. Create peer on MikroTik CHR
        $payload = [
            'interface' => $this->config['interface'],
            'public-key' => $clientKeys['public_key'],
            'allowed-address' => $clientIp . '/32',
            'name' => $name,
        ];

        $this->client->request('PUT', '/interface/wireguard/peers', $payload);

        // 4b. Fetch the newly created peer's .id from the server
        $newPeerId = null;
        $updatedPeers = $this->getPeers();
        foreach ($updatedPeers as $p) {
            if (($p['public-key'] ?? '') === $clientKeys['public_key']) {
                $newPeerId = $p['.id'] ?? null;
                break;
            }
        }

        // 5. Generate client config & client script
        $clientConfig = self::generateConfig(
            $clientIp,
            $clientKeys['private_key'],
            $serverPublicKey,
            $this->config['endpoint'],
            $this->config['client_allowed_ips']
        );

        $comment = $this->config['comment'] ?? $this->config['interface'];

        $clientScript = self::generateRscScript(
            $clientIp,
            $clientKeys['private_key'],
            $serverPublicKey,
            $this->config['endpoint'],
            $this->config['client_allowed_ips'],
            'wg-resnovae',
            $comment,
            $this->config['server_ip'] ?? '3.0.0.1',
            $this->config['subnet'] ?? '3.0.0.0/21'
        );

        return [
            '.id' => $newPeerId,
            'name' => $name,
            'ip' => $clientIp,
            'public_key' => $clientKeys['public_key'],
            'private_key' => $clientKeys['private_key'],
            'config' => $clientConfig,
            'script' => $clientScript
        ];
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
        $this->client->request('PATCH', '/interface/wireguard/peers/' . $id, $payload);
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

        $this->client->request('PATCH', '/interface/wireguard/peers/' . $id, $payload);

        return $clientKeys;
    }

    /**
     * Delete an existing peer.
     * 
     * @param string $id MikroTik rest ID (e.g. *1c).
     */
    public function deletePeer(string $id): void {
        $this->client->request('DELETE', '/interface/wireguard/peers/' . $id);
    }

    /**
     * Generate standard wireguard .conf configuration content.
     */
    public static function generateConfig(
        string $clientIp,
        string $clientPrivateKey,
        string $serverPublicKey,
        string $serverEndpoint,
        string $clientAllowedIps
    ): string {
        return <<<INI
[Interface]
PrivateKey = $clientPrivateKey
Address = $clientIp/32
DNS = 1.1.1.1

[Peer]
PublicKey = $serverPublicKey
Endpoint = $serverEndpoint
AllowedIPs = $clientAllowedIps
PersistentKeepalive = 25
INI;
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
        string $subnet = '3.0.0.0/21'
    ): string {
        $endpointParts = explode(':', $serverEndpoint);
        $endpointHost = $endpointParts[0] ?? '';
        $endpointPort = $endpointParts[1] ?? '13231';
        $comment = $comment ?: $interfaceName;

        $subnetParts = explode('/', $subnet);
        $networkAddress = $subnetParts[0];
        $mask = $subnetParts[1] ?? '21';

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

/ip firewall address-list
add address=$serverIp list=MANAGEMENT
RSC;
    }
}

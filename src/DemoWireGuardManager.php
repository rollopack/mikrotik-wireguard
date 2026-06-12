<?php

class DemoWireGuardManager {
    private array $config;
    
    public function __construct(array $config) {
        $this->config = $config;
    }

    private function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['demo_peers'])) {
            $_SESSION['demo_peers'] = [
                [
                    '.id' => '*1',
                    'interface' => 'WireGuard-ResNovae',
                    'name' => 'Enrico-Casa',
                    'public-key' => 'Xkx0S5i7MvUvN7zdTSF+icEZebIyD74uR+pc8JMzYjA=',
                    'allowed-address' => '3.0.0.2/32',
                    'rx' => 5976883,
                    'tx' => 491827,
                    'last-handshake' => '9s',
                    'current-endpoint-address' => '49.236.31.111',
                    'current-endpoint-port' => '39154'
                ],
                [
                    '.id' => '*2',
                    'interface' => 'WireGuard-ResNovae',
                    'name' => 'Ufficio-Milano',
                    'public-key' => 'bYgH77Dsfjhgfd78SDFHJKs89sdflkjsdf908sdfjks=',
                    'allowed-address' => '3.0.0.3/32',
                    'rx' => 124567890,
                    'tx' => 98765432,
                    'last-handshake' => '2m 15s',
                    'current-endpoint-address' => '93.45.122.8',
                    'current-endpoint-port' => '54321'
                ],
                [
                    '.id' => '*3',
                    'interface' => 'WireGuard-ResNovae',
                    'name' => 'Router-Cliente-Filippo',
                    'public-key' => 'AzydGaWokc1Spn7VFZlItk2ATYK0r6oFWOh3/IDFtHY=',
                    'allowed-address' => '3.0.0.4/32',
                    'rx' => 0,
                    'tx' => 0,
                    'last-handshake' => 'never',
                    'current-endpoint-address' => '',
                    'current-endpoint-port' => ''
                ]
            ];
        }
    }

    public function getServerPublicKey(): string {
        return 'SERVER_PUBLIC_KEY_DEMO_HASH_BASE64_1234567890=';
    }

    public function getPeers(): array {
        $this->initSession();
        $formatted = [];
        foreach ($_SESSION['demo_peers'] as $peer) {
            $peer['rx_formatted'] = WireGuardManager::formatBytes($peer['rx']);
            $peer['tx_formatted'] = WireGuardManager::formatBytes($peer['tx']);
            $peer['handshake_formatted'] = $peer['last-handshake'];
            $formatted[] = $peer;
        }
        return $formatted;
    }

    public function addPeer(string $name): array {
        $this->initSession();
        $peers = $this->getPeers();
        
        // Calculate next free IP
        $allocated = [];
        foreach ($_SESSION['demo_peers'] as $p) {
            if (preg_match('/^([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/', $p['allowed-address'], $m)) {
                $allocated[$m[1]] = true;
            }
        }
        $nextIp = '3.0.0.2';
        for ($i = 2; $i <= 254; $i++) {
            $candidate = '3.0.0.' . $i;
            if (!isset($allocated[$candidate])) {
                $nextIp = $candidate;
                break;
            }
        }

        $clientKeys = WireGuardManager::generateKeyPair();
        $serverPublicKey = $this->getServerPublicKey();
        
        $newPeer = [
            '.id' => '*' . uniqid(),
            'interface' => 'WireGuard-ResNovae',
            'name' => $name,
            'public-key' => $clientKeys['public_key'],
            'allowed-address' => $nextIp . '/32',
            'rx' => 0,
            'tx' => 0,
            'last-handshake' => 'never',
            'current-endpoint-address' => '',
            'current-endpoint-port' => ''
        ];
        
        $_SESSION['demo_peers'][] = $newPeer;
        
        $clientConfig = WireGuardManager::generateConfig(
            $nextIp,
            $clientKeys['private_key'],
            $serverPublicKey,
            $this->config['endpoint'],
            $this->config['client_allowed_ips']
        );

        $clientScript = WireGuardManager::generateRscScript(
            $nextIp,
            $clientKeys['private_key'],
            $serverPublicKey,
            $this->config['endpoint'],
            $this->config['client_allowed_ips']
        );

        return [
            'name' => $name,
            'ip' => $nextIp,
            'public_key' => $clientKeys['public_key'],
            'private_key' => $clientKeys['private_key'],
            'config' => $clientConfig,
            'script' => $clientScript
        ];
    }

    public function updatePeer(string $id, string $newName): void {
        $this->initSession();
        foreach ($_SESSION['demo_peers'] as &$peer) {
            if ($peer['.id'] === $id) {
                $peer['name'] = $newName;
                return;
            }
        }
    }

    public function regenerateKey(string $id): array {
        $this->initSession();
        $clientKeys = WireGuardManager::generateKeyPair();
        foreach ($_SESSION['demo_peers'] as &$peer) {
            if ($peer['.id'] === $id) {
                $peer['public-key'] = $clientKeys['public_key'];
                return $clientKeys;
            }
        }
        throw new Exception("Peer $id not found");
    }

    public function deletePeer(string $id): void {
        $this->initSession();
        foreach ($_SESSION['demo_peers'] as $key => $peer) {
            if ($peer['.id'] === $id) {
                unset($_SESSION['demo_peers'][$key]);
                $_SESSION['demo_peers'] = array_values($_SESSION['demo_peers']);
                return;
            }
        }
    }
}

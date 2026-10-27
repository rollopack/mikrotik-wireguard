<?php

require_once __DIR__ . '/../src/ClientInterface.php';

class MockMikrotikRestClient implements ClientInterface {
    public array $history = [];
    public array $responses = [];

    public function setResponse(string $method, string $path, $response) {
        $key = strtoupper($method) . ':' . $path;
        if (!isset($this->responses[$key])) {
            $this->responses[$key] = [];
        }
        $this->responses[$key][] = $response;
    }

    public function request(string $method, string $path, ?array $data = null): array {
        $this->history[] = [
            'method' => $method,
            'path' => $path,
            'data' => $data
        ];

        $key = strtoupper($method) . ':' . $path;
        if (isset($this->responses[$key]) && count($this->responses[$key]) > 0) {
            return array_shift($this->responses[$key]);
        }

        return [];
    }

    public function getPeers(): array {
        $peers = $this->request('GET', '/interface/wireguard/peers');
        $interfaceFilter = 'WireGuard-ResNovae';

        $allowedFields = ['.id', 'name', 'allowed-address', 'last-handshake',
                          'current-endpoint-address', 'public-key', 'disabled'];

        $filteredPeers = [];
        foreach ($peers as $peer) {
            if (($peer['interface'] ?? '') !== $interfaceFilter) {
                continue;
            }

            $out = [];
            foreach ($allowedFields as $f) {
                if (isset($peer[$f])) {
                    $out[$f] = $peer[$f];
                }
            }
            $out['rx_formatted'] = WireGuardManager::formatBytes($peer['rx'] ?? 0);
            $out['tx_formatted'] = WireGuardManager::formatBytes($peer['tx'] ?? 0);
            $out['handshake_formatted'] = WireGuardManager::formatHandshake($peer['last-handshake'] ?? '');

            $filteredPeers[] = $out;
        }

        return $filteredPeers;
    }

    public function getAllPeers(): array {
        return $this->request('GET', '/interface/wireguard/peers');
    }

    public function getServerPublicKey(): string {
        $interfaces = $this->request('GET', '/interface/wireguard');
        foreach ($interfaces as $iface) {
            if (($iface['name'] ?? '') === 'WireGuard-ResNovae') {
                return $iface['public-key'] ?? '';
            }
        }
        return '';
    }

    public function getInterface(): string {
        return 'WireGuard-ResNovae';
    }

    public function addPeer(array $payload): array {
        return $this->request('PUT', '/interface/wireguard/peers', $payload);
    }

    public function updatePeer(string $id, array $payload): void {
        $this->request('PATCH', '/interface/wireguard/peers/' . $id, $payload);
    }

    public function deletePeer(string $id): void {
        $this->request('DELETE', '/interface/wireguard/peers/' . $id);
    }

    public function getPppSecrets(): array {
        return $this->request('GET', '/ppp/secret');
    }

    public function getPppActive(): array {
        return $this->request('GET', '/ppp/active');
    }

    public function getInterfaceStatus(): array {
        return [
            'name' => 'WireGuard-ResNovae',
            'running' => true,
            'disabled' => false,
            'listen-port' => 13231,
            'mtu' => 1420,
            'public-key' => '',
            'comment' => '',
        ];
    }

    public function getPeersAndInterface(): array {
        return [
            'peers' => $this->getPeers(),
            'interface' => $this->getInterfaceStatus(),
        ];
    }
}

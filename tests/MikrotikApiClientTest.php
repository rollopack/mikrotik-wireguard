<?php

require_once __DIR__ . '/run_tests.php';

class TestableMikrotikApiClient extends MikrotikApiClient {
    public array $queryCalls = [];
    private array $mockResponses = [];

    public function setMockResponse(string $action, array $response): void {
        $this->mockResponses[$action] = $response;
    }

    protected function queryNativeApi(array $peerNames = [], string $action = 'get_peers', array $extraData = []): array {
        $this->queryCalls[] = [
            'peerNames' => $peerNames,
            'action' => $action,
            'extraData' => $extraData,
        ];
        return $this->mockResponses[$action] ?? [];
    }
}

class MikrotikApiClientTest extends TestCase {
    private array $baseConfig = [
        'host' => '192.168.88.1',
        'username' => 'admin',
        'password' => 'pass',
        'interface' => 'WG-Test',
        'native_api' => [
            'port' => 8728,
            'tls' => false,
            'python_script' => __DIR__ . '/../src/get_peer_data.py',
        ],
    ];

    // ── getPeers ────────────────────────────────────────────────────

    public function testGetPeersReturnsEmptyWhenNoData(): void {
        $client = new TestableMikrotikApiClient($this->baseConfig);
        $this->assertEquals([], $client->getPeers());
    }

    public function testGetPeersFiltersInterfaceEntry(): void {
        $client = new TestableMikrotikApiClient($this->baseConfig);
        $client->setMockResponse('get_peers', [
            'WG-Test' => ['public-key' => 'server_pub_key'],
            'Peer1' => [
                '.id' => '*1',
                'allowed-address' => '10.0.0.2/32',
                'last-handshake' => '5s',
                'public-key' => 'peer1_pub_key',
                'rx' => 1024,
                'tx' => 2048,
            ],
            'Peer2' => [
                '.id' => '*2',
                'allowed-address' => '10.0.0.3/32',
                'last-handshake' => 'never',
                'public-key' => 'peer2_pub_key',
                'rx' => 0,
                'tx' => 0,
            ],
        ]);

        $peers = $client->getPeers();
        $this->assertCount(2, $peers);
        $this->assertEquals('Peer1', $peers[0]['name']);
        $this->assertEquals('Peer2', $peers[1]['name']);
        $this->assertEquals('10.0.0.2/32', $peers[0]['allowed-address']);
    }

    public function testGetPeersFormatsHandshake(): void {
        $client = new TestableMikrotikApiClient($this->baseConfig);
        $client->setMockResponse('get_peers', [
            'Peer1' => [
                '.id' => '*1',
                'allowed-address' => '10.0.0.2/32',
                'last-handshake' => '20h44m42s',
                'public-key' => 'pk1',
                'rx' => 0,
                'tx' => 0,
            ],
        ]);

        $peers = $client->getPeers();
        $this->assertEquals('20h 44m 42s', $peers[0]['handshake_formatted']);
    }

    public function testGetPeersFormatsBytes(): void {
        $client = new TestableMikrotikApiClient($this->baseConfig);
        $client->setMockResponse('get_peers', [
            'Peer1' => [
                '.id' => '*1',
                'allowed-address' => '10.0.0.2/32',
                'last-handshake' => '5s',
                'public-key' => 'pk1',
                'rx' => 5976883,
                'tx' => 491827,
            ],
        ]);

        $peers = $client->getPeers();
        $this->assertEquals('5.70 MiB', $peers[0]['rx_formatted']);
        $this->assertEquals('480.30 KiB', $peers[0]['tx_formatted']);
    }

    // ── getServerPublicKey ───────────────────────────────────────

    public function testGetServerPublicKey(): void {
        $client = new TestableMikrotikApiClient($this->baseConfig);
        $client->setMockResponse('get_interface', [
            'WG-Test' => ['public-key' => 'SERVER_PUB_KEY'],
        ]);

        $key = $client->getServerPublicKey();
        $this->assertEquals('SERVER_PUB_KEY', $key);
    }

    public function testGetServerPublicKeyFallbackToFirst(): void {
        $client = new TestableMikrotikApiClient([
            'host' => '192.168.88.1',
            'username' => 'admin',
            'password' => 'pass',
            'interface' => 'NonExistent',
            'native_api' => [
                'port' => 8728,
                'tls' => false,
                'python_script' => __DIR__ . '/../src/get_peer_data.py',
            ],
        ]);
        $client->setMockResponse('get_interface', [
            'WG-Real' => ['public-key' => 'FALLBACK_KEY'],
        ]);

        $key = $client->getServerPublicKey();
        $this->assertEquals('FALLBACK_KEY', $key);
    }

    public function testGetServerPublicKeyThrowsWhenNotFound(): void {
        $client = new TestableMikrotikApiClient($this->baseConfig);
        $client->setMockResponse('get_interface', []);

        $threw = false;
        try {
            $client->getServerPublicKey();
        } catch (Exception $e) {
            $threw = true;
            $this->assertTrue(str_contains($e->getMessage(), 'not found'));
        }
        if (!$threw) {
            $this->assertTrue(false, 'Expected exception when interface not found');
        }
    }

    // ── getAllPeers ────────────────────────────────────────────────

    public function testGetAllPeers(): void {
        $client = new TestableMikrotikApiClient($this->baseConfig);
        $client->setMockResponse('get_all_peers', [
            ['name' => 'Peer1'],
        ]);

        $result = $client->getAllPeers();
        $this->assertCount(1, $result);
        $this->assertEquals('Peer1', $result[0]['name']);
    }

    // ── addPeer ────────────────────────────────────────────────────

    public function testAddPeer(): void {
        $client = new TestableMikrotikApiClient($this->baseConfig);
        $client->setMockResponse('add_peer', ['.id' => '*99']);

        $result = $client->addPeer([
            'interface' => 'WG-Test',
            'public-key' => 'new_pub_key',
            'allowed-address' => '10.0.0.5/32',
            'name' => 'NewPeer',
        ]);

        $this->assertCount(1, $client->queryCalls);
        $this->assertEquals('add_peer', $client->queryCalls[0]['action']);
        $this->assertEquals('NewPeer', $client->queryCalls[0]['extraData']['payload']['name']);
    }

    // ── updatePeer ─────────────────────────────────────────────────

    public function testUpdatePeer(): void {
        $client = new TestableMikrotikApiClient($this->baseConfig);
        $client->setMockResponse('update_peer', []);

        $client->updatePeer('*1', ['name' => 'Updated']);

        $this->assertCount(1, $client->queryCalls);
        $this->assertEquals('update_peer', $client->queryCalls[0]['action']);
        $this->assertEquals('*1', $client->queryCalls[0]['extraData']['peer_id']);
        $this->assertEquals('Updated', $client->queryCalls[0]['extraData']['update_data']['name']);
    }

    // ── deletePeer ─────────────────────────────────────────────────

    public function testDeletePeer(): void {
        $client = new TestableMikrotikApiClient($this->baseConfig);
        $client->setMockResponse('delete_peer', []);

        $client->deletePeer('*1');

        $this->assertCount(1, $client->queryCalls);
        $this->assertEquals('delete_peer', $client->queryCalls[0]['action']);
        $this->assertEquals('*1', $client->queryCalls[0]['extraData']['peer_id']);
    }

    // ── getInterface ───────────────────────────────────────────────

    public function testGetInterfaceFromConfig(): void {
        $client = new TestableMikrotikApiClient($this->baseConfig);
        $this->assertEquals('WG-Test', $client->getInterface());
    }

    public function testGetInterfaceDefault(): void {
        $client = new TestableMikrotikApiClient([
            'host' => '192.168.88.1',
            'username' => 'admin',
            'password' => 'pass',
            'native_api' => [
                'port' => 8728,
                'tls' => false,
                'python_script' => __DIR__ . '/../src/get_peer_data.py',
            ],
        ]);
        $this->assertEquals('WireGuard-ResNovae', $client->getInterface());
    }

    // ── getPppSecrets / getPppActive ───────────────────────────────

    public function testGetPppSecrets(): void {
        $client = new TestableMikrotikApiClient($this->baseConfig);
        $client->setMockResponse('get_ppp_secrets', [
            ['.id' => '*1', 'name' => 'user1', 'service' => 'sstp'],
        ]);

        $result = $client->getPppSecrets();
        $this->assertCount(1, $result);
        $this->assertEquals('user1', $result[0]['name']);
    }

    public function testGetPppActive(): void {
        $client = new TestableMikrotikApiClient($this->baseConfig);
        $client->setMockResponse('get_ppp_active', [
            ['.id' => '*1', 'name' => 'user1', 'service' => 'sstp'],
        ]);

        $result = $client->getPppActive();
        $this->assertCount(1, $result);
        $this->assertEquals('user1', $result[0]['name']);
    }

    // ── request method ─────────────────────────────────────────────

    public function testRequestThrowsInNativeMode(): void {
        $client = new TestableMikrotikApiClient($this->baseConfig);
        $threw = false;
        try {
            $client->request('GET', '/test');
        } catch (Exception $e) {
            $threw = true;
            $this->assertTrue(str_contains($e->getMessage(), 'not supported in native mode'));
        }
        if (!$threw) {
            $this->assertTrue(false, 'Expected exception when calling request() in native mode');
        }
    }
}

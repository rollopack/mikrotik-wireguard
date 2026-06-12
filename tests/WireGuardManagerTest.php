<?php

require_once __DIR__ . '/run_tests.php';

// Mock REST client for testing WireGuardManager without a real MikroTik CHR
class MockMikrotikRestClient {
    public array $history = [];
    public array $responses = [];

    public function setResponse(string $method, string $path, $response) {
        $this->responses[strtoupper($method) . ':' . $path] = $response;
    }

    public function request(string $method, string $path, array $data = null): array {
        $this->history[] = [
            'method' => $method,
            'path' => $path,
            'data' => $data
        ];

        $key = strtoupper($method) . ':' . $path;
        if (array_key_exists($key, $this->responses)) {
            return $this->responses[$key];
        }

        return [];
    }
}

class WireGuardManagerTest extends TestCase {
    
    public function testMaxPeers() {
        $this->assertEquals(254, WireGuardManager::maxPeers('3.0.0.0/24', '3.0.0.1'));
        $this->assertEquals(2046, WireGuardManager::maxPeers('3.0.0.0/21', '3.0.0.1'));
        $this->assertEquals(255, WireGuardManager::maxPeers('3.0.0.0/24', '3.0.0.0'));
        $this->assertEquals(255, WireGuardManager::maxPeers('3.0.0.0/24'));
        $this->assertEquals(65534, WireGuardManager::maxPeers('10.0.0.0/16', '10.0.0.1'));
    }

    public function testKeyGeneration() {
        // Run key generation
        $keys = WireGuardManager::generateKeyPair();
        
        $this->assertTrue(isset($keys['private_key']), 'Private key should be set');
        $this->assertTrue(isset($keys['public_key']), 'Public key should be set');
        
        $privBytes = base64_decode($keys['private_key']);
        $pubBytes = base64_decode($keys['public_key']);
        
        $this->assertEquals(32, strlen($privBytes), 'Private key should be 32 bytes');
        $this->assertEquals(32, strlen($pubBytes), 'Public key should be 32 bytes');
        
        // Derive public key from private key to verify math correctness
        $derivedPubBytes = sodium_crypto_scalarmult_base($privBytes);
        $derivedPubB64 = base64_encode($derivedPubBytes);
        
        $this->assertEquals($keys['public_key'], $derivedPubB64, 'Public key derivation should match');
    }

    public function testIpAllocation() {
        // Initialize manager with a mock client
        $manager = new WireGuardManager(new MockMikrotikRestClient(), [
            'subnet' => '3.0.0.0/24',
            'server_ip' => '3.0.0.1'
        ]);

        // Scenario 1: Empty peer list
        $peersEmpty = [];
        $ip = $manager->calculateNextFreeIp($peersEmpty);
        $this->assertEquals('3.0.0.2', $ip);

        // Scenario 2: 3.0.0.2 is taken
        $peersOne = [
            ['allowed-address' => '3.0.0.2/32']
        ];
        $ip = $manager->calculateNextFreeIp($peersOne);
        $this->assertEquals('3.0.0.3', $ip);

        // Scenario 3: 3.0.0.2 and 3.0.0.3 are taken
        $peersTwo = [
            ['allowed-address' => '3.0.0.2/32'],
            ['allowed-address' => '3.0.0.3/32']
        ];
        $ip = $manager->calculateNextFreeIp($peersTwo);
        $this->assertEquals('3.0.0.4', $ip);

        // Scenario 4: Gap in allocation (3.0.0.2 and 3.0.0.4 taken)
        $peersGap = [
            ['allowed-address' => '3.0.0.2/32'],
            ['allowed-address' => '3.0.0.4/32']
        ];
        $ip = $manager->calculateNextFreeIp($peersGap);
        $this->assertEquals('3.0.0.3', $ip);
        
        // Scenario 5: Multiple allowed IPs inside one allowed-address string or random spaces
        $peersComplex = [
            ['allowed-address' => '3.0.0.2/32,192.168.1.0/24'],
            ['allowed-address' => '3.0.0.3/32']
        ];
        $ip = $manager->calculateNextFreeIp($peersComplex);
        $this->assertEquals('3.0.0.4', $ip);
    }

    public function testConfigFormatting() {
        $config = WireGuardManager::generateConfig(
            '3.0.0.5',
            'client_private_key_base64_goes_here',
            'server_public_key_base64_goes_here',
            'mailserver.resnovae.it:13231',
            '3.0.0.0/24,192.168.111.0/24'
        );

        $this->assertTrue(str_contains($config, '[Interface]'), 'Config should contain [Interface] section');
        $this->assertTrue(str_contains($config, 'PrivateKey = client_private_key_base64_goes_here'), 'Config should contain client private key');
        $this->assertTrue(str_contains($config, 'Address = 3.0.0.5/32'), 'Config should contain address');
        $this->assertTrue(str_contains($config, '[Peer]'), 'Config should contain [Peer] section');
        $this->assertTrue(str_contains($config, 'PublicKey = server_public_key_base64_goes_here'), 'Config should contain server public key');
        $this->assertTrue(str_contains($config, 'Endpoint = mailserver.resnovae.it:13231'), 'Config should contain endpoint');
        $this->assertTrue(str_contains($config, 'AllowedIPs = 3.0.0.0/24,192.168.111.0/24'), 'Config should contain allowed IPs');
        $this->assertTrue(str_contains($config, 'PersistentKeepalive = 25'), 'Config should contain persistent keepalive');
    }

    public function testGetPeers() {
        $mockClient = new MockMikrotikRestClient();
        $mockClient->setResponse('GET', '/interface/wireguard/peers', [
            [
                '.id' => '*1c',
                'interface' => 'WireGuard-ResNovae',
                'name' => 'Enrico-Casa',
                'public-key' => 'Xkx0S5i7MvUvN7zdTSF+icEZebIyD74uR+pc8JMzYjA=',
                'allowed-address' => '3.0.0.2/32',
                'rx' => 5976883,
                'tx' => 491827,
                'last-handshake' => '9s'
            ]
        ]);

        $manager = new WireGuardManager($mockClient, [
            'interface' => 'WireGuard-ResNovae',
            'subnet' => '3.0.0.0/24',
            'server_ip' => '3.0.0.1'
        ]);

        $peers = $manager->getPeers();

        $this->assertEquals(1, count($peers));
        $this->assertEquals('Enrico-Casa', $peers[0]['name']);
        $this->assertEquals('3.0.0.2/32', $peers[0]['allowed-address']);
        $this->assertEquals('5.70 MiB', $peers[0]['rx_formatted']);
    }

    public function testAddPeer() {
        $mockClient = new MockMikrotikRestClient();
        // Mock getPeers response (empty)
        $mockClient->setResponse('GET', '/interface/wireguard/peers', []);
        // Mock getServerPublicKey response
        $mockClient->setResponse('GET', '/interface/wireguard', [
            [
                'name' => 'WireGuard-ResNovae',
                'public-key' => 'SERVER_PUBLIC_KEY_BASE64'
            ]
        ]);
        // Mock the PUT response
        $mockClient->setResponse('PUT', '/interface/wireguard/peers', [
            '.id' => '*1d'
        ]);

        $manager = new WireGuardManager($mockClient, [
            'interface' => 'WireGuard-ResNovae',
            'subnet' => '3.0.0.0/24',
            'server_ip' => '3.0.0.1',
            'endpoint' => 'mailserver.resnovae.it:13231',
            'client_allowed_ips' => '3.0.0.0/24,192.168.111.0/24'
        ]);

        $result = $manager->addPeer('Test-Client-New');

        $this->assertEquals('Test-Client-New', $result['name']);
        $this->assertEquals('3.0.0.2', $result['ip']);
        
        // Find PUT request in client history
        $putRequest = null;
        foreach ($mockClient->history as $req) {
            if ($req['method'] === 'PUT' && $req['path'] === '/interface/wireguard/peers') {
                $putRequest = $req;
                break;
            }
        }

        $this->assertNotEmpty($putRequest, 'A PUT request should have been made to create the peer');
        $this->assertEquals('WireGuard-ResNovae', $putRequest['data']['interface']);
        $this->assertEquals('3.0.0.2/32', $putRequest['data']['allowed-address']);
        $this->assertEquals('Test-Client-New', $putRequest['data']['name']);
    }
}

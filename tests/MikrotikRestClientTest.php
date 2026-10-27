<?php

require_once __DIR__ . '/run_tests.php';
require_once __DIR__ . '/../src/MikrotikRestClient.php';

class MikrotikRestClientTest extends TestCase {
    private function readBaseUrl(MikrotikRestClient $client): string {
        $ref = new ReflectionClass($client);
        $prop = $ref->getProperty('baseUrl');
        return $prop->getValue($client);
    }

    public function testBaseUrlWithBareHost(): void {
        $client = new MikrotikRestClient('192.168.88.1', 'admin', 'pass');
        $this->assertEquals('https://192.168.88.1/rest', $this->readBaseUrl($client));
    }

    public function testBaseUrlWithHttpsScheme(): void {
        $client = new MikrotikRestClient('https://192.168.88.1', 'admin', 'pass');
        $this->assertEquals('https://192.168.88.1/rest', $this->readBaseUrl($client));
    }

    public function testBaseUrlWithHttpScheme(): void {
        $client = new MikrotikRestClient('http://192.168.88.1', 'admin', 'pass');
        $this->assertEquals('http://192.168.88.1/rest', $this->readBaseUrl($client));
    }

    public function testBaseUrlWithTrailingSlash(): void {
        $client = new MikrotikRestClient('https://192.168.88.1/', 'admin', 'pass');
        $this->assertEquals('https://192.168.88.1/rest', $this->readBaseUrl($client));
    }

    public function testBaseUrlDoesNotDuplicateRest(): void {
        $client = new MikrotikRestClient('https://192.168.88.1/rest', 'admin', 'pass');
        $this->assertEquals('https://192.168.88.1/rest', $this->readBaseUrl($client));
    }

    public function testBaseUrlWithHostname(): void {
        $client = new MikrotikRestClient('router.example.com', 'admin', 'pass');
        $this->assertEquals('https://router.example.com/rest', $this->readBaseUrl($client));
    }

    public function testGetInterface(): void {
        $client = new MikrotikRestClient('192.168.88.1', 'admin', 'pass', false, 10, 'WG-Interface');
        $this->assertEquals('WG-Interface', $client->getInterface());
    }

    private function buildUrl(MikrotikRestClient $client, string $path, array $query = []): string {
        $method = new ReflectionMethod($client, 'buildUrl');
        return $method->invoke($client, $path, $query);
    }

    public function testBuildUrlWithoutQueryIsUnchanged(): void {
        $client = new MikrotikRestClient('192.168.88.1', 'admin', 'pass');

        $this->assertEquals(
            'https://192.168.88.1/rest/interface/wireguard/peers',
            $this->buildUrl($client, '/interface/wireguard/peers')
        );
    }

    public function testBuildUrlEncodesQueryValuesButKeepsParameterNames(): void {
        $client = new MikrotikRestClient('192.168.88.1', 'admin', 'pass');

        $this->assertEquals(
            'https://192.168.88.1/rest/interface/wireguard/peers?.proplist=.id%2Cname%2Cdisabled&interface=wg-users',
            $this->buildUrl($client, '/interface/wireguard/peers', [
                '.proplist' => '.id,name,disabled',
                'interface' => 'wg-users',
            ])
        );
    }

    public function testPeerProjectionCoversEveryFieldTheDashboardRenders(): void {
        $fields = (new ReflectionClass(MikrotikRestClient::class))->getConstant('PEER_FIELDS');

        foreach (['.id', 'name', 'interface', 'allowed-address', 'last-handshake',
                  'current-endpoint-address', 'disabled', 'rx', 'tx'] as $field) {
            $this->assertTrue(in_array($field, $fields, true), "PEER_FIELDS is missing {$field}");
        }
    }

    public function testPeersAndInterfaceCombinesBothPayloads(): void {
        $client = new class('192.168.88.1', 'admin', 'pass') extends MikrotikRestClient {
            public function getPeers(): array {
                return [['name' => 'Peer1']];
            }
            public function getInterfaceStatus(): array {
                return ['name' => 'wg-users', 'running' => true];
            }
        };

        $snapshot = $client->getPeersAndInterface();

        $this->assertCount(1, $snapshot['peers']);
        $this->assertEquals('wg-users', $snapshot['interface']['name']);
    }

    public function testRequestThrowsOnNetworkError(): void {
        $client = new MikrotikRestClient('192.0.2.1', 'admin', 'pass', false, 1);
        $threw = false;
        try {
            $client->request('GET', '/test');
        } catch (Exception $e) {
            $threw = true;
            $this->assertTrue(str_contains($e->getMessage(), 'Network request failed'),
                "Expected network error, got: " . $e->getMessage());
        }
        if (!$threw) {
            $this->assertTrue(false, "Expected network error exception");
        }
    }
}

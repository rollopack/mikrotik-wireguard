<?php

require_once __DIR__ . '/run_tests.php';
require_once __DIR__ . '/MockMikrotikRestClient.php';

class FailingPppSecretsClient extends MockMikrotikRestClient {
    public function getPppSecrets(): array {
        throw new Exception('secrets unavailable');
    }
}

class VpnIpExporterTest extends TestCase {

    public function testWireguardIpsAreUniqueAndSorted(): void {
        $client = new MockMikrotikRestClient();
        $client->setResponse('GET', '/interface/wireguard/peers', [
            ['.id' => '*1', 'interface' => 'WireGuard-ResNovae', 'allowed-address' => '3.0.0.10/32'],
            ['.id' => '*2', 'interface' => 'WireGuard-ResNovae', 'allowed-address' => '3.0.0.2/32'],
            ['.id' => '*3', 'interface' => 'WireGuard-ResNovae', 'allowed-address' => '3.0.0.10/32'],
        ]);

        $exporter = new VpnIpExporter($client);
        $this->assertEquals(['3.0.0.2', '3.0.0.10'], $exporter->wireguardIps());
    }

    public function testServiceIpsFiltersByServiceAndDisabled(): void {
        $secrets = [
            ['name' => 'a', 'service' => 'sstp', 'remote-address' => '10.0.0.5', 'disabled' => 'no'],
            ['name' => 'b', 'service' => 'sstp', 'remote-address' => '10.0.0.6', 'disabled' => 'yes'],
            ['name' => 'c', 'service' => 'pptp', 'remote-address' => '10.0.0.7', 'disabled' => 'no'],
            ['name' => 'd', 'service' => 'sstp', 'remote-address' => 'not-an-ip', 'disabled' => 'no'],
        ];

        $client = new MockMikrotikRestClient();
        $client->setResponse('GET', '/ppp/secret', $secrets);
        $client->setResponse('GET', '/ppp/secret', $secrets);

        $exporter = new VpnIpExporter($client);
        $this->assertEquals(['10.0.0.5'], $exporter->serviceIps('sstp'));
        $this->assertEquals(['10.0.0.7'], $exporter->serviceIps('pptp'));
    }

    public function testServiceIpsFallsBackToActive(): void {
        $client = new MockMikrotikRestClient();
        $client->setResponse('GET', '/ppp/secret', []);
        $client->setResponse('GET', '/ppp/active', [
            ['name' => 'a', 'service' => 'sstp', 'address' => '10.0.0.9'],
            ['name' => 'b', 'service' => 'pptp', 'address' => '10.0.0.8'],
        ]);

        $exporter = new VpnIpExporter($client);
        $this->assertEquals(['10.0.0.9'], $exporter->serviceIps('sstp', true));
    }

    public function testServiceIpsDoesNotFallbackWhenDisabled(): void {
        $client = new MockMikrotikRestClient();
        $client->setResponse('GET', '/ppp/secret', []);
        $client->setResponse('GET', '/ppp/active', [
            ['name' => 'a', 'service' => 'sstp', 'address' => '10.0.0.9'],
        ]);

        $exporter = new VpnIpExporter($client);
        $this->assertEquals([], $exporter->serviceIps('sstp', false));
    }

    public function testServiceIpsCapturesErrorWithoutThrowing(): void {
        $client = new FailingPppSecretsClient();
        $exporter = new VpnIpExporter($client);
        $error = null;

        $ips = $exporter->serviceIps('sstp', false, $error);

        $this->assertEquals([], $ips);
        $this->assertEquals('secrets unavailable', $error);
    }
}

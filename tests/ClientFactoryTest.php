<?php

require_once __DIR__ . '/run_tests.php';

class ClientFactoryTest extends TestCase {
    private array $baseConfig = [
        'host' => '192.168.88.1',
        'username' => 'admin',
        'password' => 'pass',
        'interface' => 'WG-Test',
    ];

    public function testCreateRestMode(): void {
        $config = $this->baseConfig;
        $config['api_mode'] = 'rest';
        $client = ClientFactory::create($config);
        $this->assertTrue($client instanceof MikrotikRestClient);
    }

    public function testCreateRestModeDefault(): void {
        $config = $this->baseConfig;
        $client = ClientFactory::create($config);
        $this->assertTrue($client instanceof MikrotikRestClient);
    }

    public function testCreateNativeMode(): void {
        $config = $this->baseConfig;
        $config['api_mode'] = 'native';
        $config['native_api'] = [
            'port' => 8728,
            'tls' => false,
            'python_script' => __DIR__ . '/../src/get_peer_data.py',
        ];
        $client = ClientFactory::create($config);
        $this->assertTrue($client instanceof MikrotikApiClient);
    }

    public function testCreateNativeModeWithoutNativeApiThrows(): void {
        $config = $this->baseConfig;
        $config['api_mode'] = 'native';
        $threw = false;
        try {
            ClientFactory::create($config);
        } catch (InvalidArgumentException $e) {
            $threw = true;
            $this->assertTrue(str_contains($e->getMessage(), 'native_api configuration required'));
        }
        if (!$threw) {
            $this->assertTrue(false, 'Expected InvalidArgumentException');
        }
    }

    public function testCreateNativeModeWithEmptyNativeApiThrows(): void {
        $config = $this->baseConfig;
        $config['api_mode'] = 'native';
        $config['native_api'] = [];
        $threw = false;
        try {
            ClientFactory::create($config);
        } catch (InvalidArgumentException $e) {
            $threw = true;
            $this->assertTrue(str_contains($e->getMessage(), 'native_api configuration required'));
        }
        if (!$threw) {
            $this->assertTrue(false, 'Expected InvalidArgumentException');
        }
    }

    public function testCreateUnknownModeThrows(): void {
        $config = $this->baseConfig;
        $config['api_mode'] = 'unknown_mode';
        $threw = false;
        try {
            ClientFactory::create($config);
        } catch (InvalidArgumentException $e) {
            $threw = true;
            $this->assertTrue(str_contains($e->getMessage(), 'Unknown api_mode'));
        }
        if (!$threw) {
            $this->assertTrue(false, 'Expected InvalidArgumentException');
        }
    }

    public function testRestModePassesSslVerify(): void {
        $config = $this->baseConfig;
        $config['api_mode'] = 'rest';
        $config['ssl_verify'] = true;
        $client = ClientFactory::create($config);
        $this->assertTrue($client instanceof MikrotikRestClient);
    }

    public function testRestModePassesInterface(): void {
        $config = $this->baseConfig;
        $config['api_mode'] = 'rest';
        $client = ClientFactory::create($config);
        $this->assertEquals('WG-Test', $client->getInterface());
    }
}

<?php

require_once __DIR__ . '/run_tests.php';

class ConfigManagerTest extends TestCase
{
    public function setUp(): void
    {
        $refl = new ReflectionClass(ConfigManager::class);
        $serversProp = $refl->getProperty('availableServers');
        $serversProp->setAccessible(true);
        $serversProp->setValue(null);
        $configProp = $refl->getProperty('activeConfig');
        $configProp->setAccessible(true);
        $configProp->setValue(null);
        unset($_GET['server']);
    }

    public function testGetAvailableServersReturnsBoth(): void
    {
        $servers = ConfigManager::getAvailableServers();
        $this->assertNotEmpty($servers);
        $this->assertTrue(isset($servers['resnovae']), 'resnovae config should be found');
        $this->assertTrue(isset($servers['dinomusa']), 'dinomusa config should be found');
    }

    public function testGetAvailableServersHasMetadata(): void
    {
        $servers = ConfigManager::getAvailableServers();
        $this->assertEquals('dinomusa', $servers['dinomusa']['key']);
        $this->assertNotEmpty($servers['dinomusa']['name']);
        $this->assertNotEmpty($servers['dinomusa']['host']);
        $this->assertEquals('resnovae', $servers['resnovae']['key']);
    }

    public function testGetActiveServerKeyDefaultsToFirst(): void
    {
        $key = ConfigManager::getActiveServerKey();
        $this->assertNotEmpty($key);
        $servers = ConfigManager::getAvailableServers();
        $this->assertTrue(isset($servers[$key]));
    }

    public function testGetActiveServerKeyFromGetParam(): void
    {
        $_GET['server'] = 'resnovae';
        $key = ConfigManager::getActiveServerKey();
        $this->assertEquals('resnovae', $key);
    }

    public function testGetActiveServerKeyFromGetParamPrefersValid(): void
    {
        $_GET['server'] = 'nonexistent';
        $key = ConfigManager::getActiveServerKey();
        $servers = ConfigManager::getAvailableServers();
        $this->assertTrue(isset($servers[$key]));
        $this->assertNotEquals('nonexistent', $key);
    }

    public function testResolveConfigReturnsArray(): void
    {
        $config = ConfigManager::resolveConfig();
        $this->assertTrue(is_array($config));
        $this->assertTrue(isset($config['host']));
        $this->assertTrue(isset($config['interface']));
        $this->assertTrue(isset($config['subnet']));
        $this->assertTrue(isset($config['endpoint']));
    }

    public function testResolveConfigAddsServerKey(): void
    {
        $config = ConfigManager::resolveConfig();
        $this->assertTrue(isset($config['_server_key']));
        $this->assertNotEmpty($config['_server_key']);
    }

    public function testResolveConfigWithGetParam(): void
    {
        $_GET['server'] = 'dinomusa';
        $config = ConfigManager::resolveConfig();
        $this->assertEquals('dinomusa', $config['_server_key']);
        $this->assertEquals('45.145.201.102', $config['host']);
    }

    public function testResolveConfigWithResnovae(): void
    {
        $_GET['server'] = 'resnovae';
        $config = ConfigManager::resolveConfig();
        $this->assertEquals('resnovae', $config['_server_key']);
        $this->assertEquals('192.168.111.253', $config['host']);
        $this->assertEquals('rest', $config['api_mode']);
    }

    public function testGetAvailableServersOrdered(): void
    {
        $servers = ConfigManager::getAvailableServers();
        $keys = array_keys($servers);
        $sorted = $keys;
        sort($sorted);
        $this->assertEquals($sorted, $keys, 'Servers should be sorted alphabetically');
    }

    public function testResnovaeConfigValid(): void
    {
        $config = require __DIR__ . '/../configs/resnovae.php';
        $this->assertEquals('rest', $config['api_mode']);
        $this->assertEquals('3.0.0.0/21', $config['subnet']);
        $this->assertEquals('192.168.111.253', $config['host']);
        $this->assertEquals('WireGuard-ResNovae', $config['interface']);
    }

    public function testDinomasaConfigValid(): void
    {
        $config = require __DIR__ . '/../configs/dinomusa.php';
        $this->assertEquals('native', $config['api_mode']);
        $this->assertEquals('10.200.200.10/24', $config['subnet']);
        $this->assertEquals('45.145.201.102', $config['host']);
        $this->assertEquals('wg-users', $config['interface']);
    }
}

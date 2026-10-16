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
        $this->assertTrue(isset($servers['01-resnovae']), '01-resnovae config should be found');
        $this->assertTrue(isset($servers['02-dinomusa']), '02-dinomusa config should be found');
    }

    public function testGetAvailableServersHasMetadata(): void
    {
        $servers = ConfigManager::getAvailableServers();
        $this->assertEquals('02-dinomusa', $servers['02-dinomusa']['key']);
        $this->assertNotEmpty($servers['02-dinomusa']['name']);
        $this->assertNotEmpty($servers['02-dinomusa']['host']);
        $this->assertEquals('01-resnovae', $servers['01-resnovae']['key']);
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
        $_GET['server'] = '01-resnovae';
        $key = ConfigManager::getActiveServerKey();
        $this->assertEquals('01-resnovae', $key);
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
        $_GET['server'] = '02-dinomusa';
        $config = ConfigManager::resolveConfig();
        $this->assertEquals('02-dinomusa', $config['_server_key']);
        $this->assertEquals('mailserver.dinomusa.it', $config['host']);
    }

    public function testResolveConfigWithResnovae(): void
    {
        $_GET['server'] = '01-resnovae';
        $config = ConfigManager::resolveConfig();
        $this->assertEquals('01-resnovae', $config['_server_key']);
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

    public function testServerExistsValid(): void
    {
        $this->assertTrue(ConfigManager::serverExists('01-resnovae'));
    }

    public function testServerExistsUnknown(): void
    {
        $this->assertFalse(ConfigManager::serverExists('nonexistent'));
    }

    public function testServerExistsEmpty(): void
    {
        $this->assertFalse(ConfigManager::serverExists(''));
    }

    public function testValidateRequestedServerAcceptsAbsentParam(): void
    {
        $this->assertNull(ConfigManager::validateRequestedServer(null));
    }

    public function testValidateRequestedServerAcceptsEmptyParam(): void
    {
        $this->assertNull(ConfigManager::validateRequestedServer(''));
    }

    public function testValidateRequestedServerAcceptsKnownServer(): void
    {
        $this->assertNull(ConfigManager::validateRequestedServer('01-resnovae'));
    }

    public function testValidateRequestedServerRejectsUnknownServer(): void
    {
        $this->assertEquals('Unknown server: nonexistent', ConfigManager::validateRequestedServer('nonexistent'));
    }

    public function testValidateRequestedServerRejectsNonString(): void
    {
        $this->assertEquals('Unknown server: invalid', ConfigManager::validateRequestedServer(['x']));
    }

    public function testResnovaeConfigValid(): void
    {
        $config = require __DIR__ . '/../configs/01-resnovae.php';
        $this->assertEquals('rest', $config['api_mode']);
        $this->assertEquals('3.0.0.0/21', $config['subnet']);
        $this->assertEquals('192.168.111.253', $config['host']);
        $this->assertEquals('WireGuard-ResNovae', $config['interface']);
    }

    public function testDinomasaConfigValid(): void
    {
        $config = require __DIR__ . '/../configs/02-dinomusa.php';
        $this->assertEquals('native', $config['api_mode']);
        $this->assertEquals('10.200.200.10/24', $config['subnet']);
        $this->assertEquals('mailserver.dinomusa.it', $config['host']);
        $this->assertEquals('wg-users', $config['interface']);
    }
}

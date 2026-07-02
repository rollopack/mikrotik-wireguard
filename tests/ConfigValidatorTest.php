<?php

require_once __DIR__ . '/run_tests.php';

class ConfigValidatorTest extends TestCase {
    private array $validConfig = [
        'subnet' => '3.0.0.0/21',
        'server_ip' => '3.0.0.1',
        'interface' => 'WireGuard-ResNovae',
        'endpoint' => 'vpn.example.com:13231',
        'client_allowed_ips' => '3.0.0.0/21,192.168.1.0/24',
        'dnat_base' => 30000,
        'dnat_multiplier' => 1000,
    ];

    public function testValidConfigPasses() {
        ConfigValidator::validate($this->validConfig);
        $this->assertTrue(true);
    }

    public function testMissingSubnet() {
        $config = $this->validConfig;
        unset($config['subnet']);
        $this->expectValidationException("Missing required config key: 'subnet'", $config);
    }

    public function testMissingServerIp() {
        $config = $this->validConfig;
        unset($config['server_ip']);
        $this->expectValidationException("Missing required config key: 'server_ip'", $config);
    }

    public function testMissingInterface() {
        $config = $this->validConfig;
        unset($config['interface']);
        $this->expectValidationException("Missing required config key: 'interface'", $config);
    }

    public function testMissingEndpoint() {
        $config = $this->validConfig;
        unset($config['endpoint']);
        $this->expectValidationException("Missing required config key: 'endpoint'", $config);
    }

    public function testMissingClientAllowedIps() {
        $config = $this->validConfig;
        unset($config['client_allowed_ips']);
        $this->expectValidationException("Missing required config key: 'client_allowed_ips'", $config);
    }

    public function testEmptySubnet() {
        $config = $this->validConfig;
        $config['subnet'] = '';
        $this->expectValidationException("Missing required config key: 'subnet'", $config);
    }

    public function testInvalidSubnetFormat() {
        $config = $this->validConfig;
        $config['subnet'] = 'not-a-subnet';
        $this->expectValidationException("Invalid subnet format", $config);
    }

    public function testSubnetMaskOutOfRange() {
        $config = $this->validConfig;
        $config['subnet'] = '3.0.0.0/31';
        $this->expectValidationException("Subnet mask must be between", $config);
    }

    public function testSubnetMaskTooLow() {
        $config = $this->validConfig;
        $config['subnet'] = '3.0.0.0/0';
        $this->expectValidationException("Subnet mask must be between", $config);
    }

    public function testServerIpOutsideSubnet() {
        $config = $this->validConfig;
        $config['subnet'] = '10.0.0.0/24';
        $config['server_ip'] = '192.168.1.1';
        $this->expectValidationException("is outside subnet", $config);
    }

    public function testServerIpIsNetworkAddress() {
        $config = $this->validConfig;
        $config['subnet'] = '10.0.0.0/24';
        $config['server_ip'] = '10.0.0.0';
        $this->expectValidationException("cannot be the network address", $config);
    }

    public function testServerIpIsBroadcast() {
        $config = $this->validConfig;
        $config['subnet'] = '10.0.0.0/24';
        $config['server_ip'] = '10.0.0.255';
        $this->expectValidationException("cannot be the broadcast address", $config);
    }

    public function testInvalidEndpointFormat() {
        $config = $this->validConfig;
        $config['endpoint'] = 'not-an-endpoint';
        $this->expectValidationException("Invalid endpoint format", $config);
    }

    public function testEndpointMissingPort() {
        $config = $this->validConfig;
        $config['endpoint'] = 'vpn.example.com';
        $this->expectValidationException("Invalid endpoint format", $config);
    }

    public function testEndpointPortOutOfRange() {
        $config = $this->validConfig;
        $config['endpoint'] = 'vpn.example.com:0';
        $this->expectValidationException("Endpoint port must be 1-65535", $config);
    }

    public function testEndpointPortTooHigh() {
        $config = $this->validConfig;
        $config['endpoint'] = 'vpn.example.com:70000';
        $this->expectValidationException("Endpoint port must be 1-65535", $config);
    }

    public function testInvalidClientAllowedIpsCidr() {
        $config = $this->validConfig;
        $config['client_allowed_ips'] = 'not-a-cidr';
        $this->expectValidationException("Invalid CIDR in client_allowed_ips", $config);
    }

    public function testClientAllowedIpsMaskOutOfRange() {
        $config = $this->validConfig;
        $config['client_allowed_ips'] = '10.0.0.0/33';
        $this->expectValidationException("Invalid mask /33", $config);
    }

    public function testDnatBaseOutOfRange() {
        $config = $this->validConfig;
        $config['dnat_base'] = 0;
        $this->expectValidationException("dnat_base must be integer 1-65535", $config);
    }

    public function testDnatMultiplierTooHigh() {
        $config = $this->validConfig;
        $config['dnat_multiplier'] = 70000;
        $this->expectValidationException("dnat_multiplier must be integer 1-65535", $config);
    }

    public function testDnatFormulaOverflow() {
        $config = $this->validConfig;
        $config['subnet'] = '10.0.0.0/8';
        $config['server_ip'] = '10.0.0.1';
        $config['dnat_base'] = 60000;
        $config['dnat_multiplier'] = 100;
        $this->expectValidationException("DNAT formula overflow", $config);
    }

    public function testDnatFormulaWithValidWideSubnet() {
        $config = $this->validConfig;
        $config['subnet'] = '10.0.0.0/16';
        $config['server_ip'] = '10.0.0.1';
        $config['dnat_base'] = 1000;
        $config['dnat_multiplier'] = 100;
        ConfigValidator::validate($config);
        $this->assertTrue(true);
    }

    public function testValidNativeApiConfig() {
        $config = $this->validConfig;
        $config['api_mode'] = 'native';
        $config['native_api'] = [
            'port' => 8728,
            'tls' => false,
            'python_script' => __DIR__ . '/../src/get_peer_data.py',
        ];
        ConfigValidator::validate($config);
        $this->assertTrue(true);
    }

    public function testValidNativeApiConfigWithTls() {
        $config = $this->validConfig;
        $config['api_mode'] = 'native';
        $config['native_api'] = [
            'port' => 8729,
            'tls' => true,
            'python_script' => __DIR__ . '/../src/get_peer_data.py',
        ];
        ConfigValidator::validate($config);
        $this->assertTrue(true);
    }

    public function testNativeApiConfigRequiredWhenNativeMode() {
        $config = $this->validConfig;
        $config['api_mode'] = 'native';
        $this->expectValidationException("native_api configuration required", $config);
    }

    public function testNativeApiConfigMustBeArray() {
        $config = $this->validConfig;
        $config['api_mode'] = 'native';
        $config['native_api'] = 'not-an-array';
        $this->expectValidationException("native_api configuration required", $config);
    }

    public function testNativeApiPortRequired() {
        $config = $this->validConfig;
        $config['api_mode'] = 'native';
        $config['native_api'] = [
            'tls' => false,
            'python_script' => __DIR__ . '/../src/get_peer_data.py',
        ];
        $this->expectValidationException("native_api.port must be an integer", $config);
    }

    public function testNativeApiPortMustBe8728or8729() {
        $config = $this->validConfig;
        $config['api_mode'] = 'native';
        $config['native_api'] = [
            'port' => 9999,
            'tls' => false,
            'python_script' => __DIR__ . '/../src/get_peer_data.py',
        ];
        $this->expectValidationException("native_api.port must be 8728 (plain) or 8729 (TLS)", $config);
    }

    public function testNativeApiTlsRequired() {
        $config = $this->validConfig;
        $config['api_mode'] = 'native';
        $config['native_api'] = [
            'port' => 8728,
            'python_script' => __DIR__ . '/../src/get_peer_data.py',
        ];
        $this->expectValidationException("native_api.tls must be a boolean", $config);
    }

    public function testNativeApiTlsTrueRequiresPort8729() {
        $config = $this->validConfig;
        $config['api_mode'] = 'native';
        $config['native_api'] = [
            'port' => 8728,
            'tls' => true,
            'python_script' => __DIR__ . '/../src/get_peer_data.py',
        ];
        $this->expectValidationException("When native_api.tls is true, native_api.port must be 8729", $config);
    }

    public function testNativeApiTlsFalseRequiresPort8728() {
        $config = $this->validConfig;
        $config['api_mode'] = 'native';
        $config['native_api'] = [
            'port' => 8729,
            'tls' => false,
            'python_script' => __DIR__ . '/../src/get_peer_data.py',
        ];
        $this->expectValidationException("When native_api.tls is false, native_api.port should be 8728", $config);
    }

    public function testNativeApiPythonScriptRequired() {
        $config = $this->validConfig;
        $config['api_mode'] = 'native';
        $config['native_api'] = [
            'port' => 8728,
            'tls' => false,
        ];
        $this->expectValidationException("native_api.python_script must be a non-empty string", $config);
    }

    public function testNativeApiPythonScriptMustExist() {
        $config = $this->validConfig;
        $config['api_mode'] = 'native';
        $config['native_api'] = [
            'port' => 8728,
            'tls' => false,
            'python_script' => '/nonexistent/path.py',
        ];
        $this->expectValidationException("native_api.python_script file not found", $config);
    }

    private function expectValidationException(string $expectedMessage, array $config): void {
        $threw = false;
        try {
            ConfigValidator::validate($config);
        } catch (InvalidArgumentException $e) {
            $threw = true;
            $this->assertTrue(str_contains($e->getMessage(), $expectedMessage),
                "Expected message containing '$expectedMessage', got: '" . $e->getMessage() . "'");
        }
        if (!$threw) {
            $this->assertTrue(false, "Expected InvalidArgumentException with message containing '$expectedMessage', but no exception was thrown");
        }
    }
}

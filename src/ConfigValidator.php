<?php

require_once __DIR__ . '/WireGuardManager.php';

class ConfigValidator {
    public static function validate(array $config): void {
        self::requireKey($config, 'subnet');
        self::requireKey($config, 'server_ip');
        self::requireKey($config, 'interface');
        self::requireKey($config, 'endpoint');
        self::requireKey($config, 'client_allowed_ips');

        // Validate native_api config if api_mode is native
        $apiMode = $config['api_mode'] ?? 'rest';
        if ($apiMode === 'native') {
            self::validateNativeApiConfig($config);
        }

        self::validateSubnet($config['subnet'], $config['server_ip']);
        self::validateEndpoint($config['endpoint']);
        self::validateClientAllowedIps($config['client_allowed_ips']);
        self::validateDnatFormula($config);
        self::validateExportMode($config);
    }

    private static function validateNativeApiConfig(array $config): void {
        if (!isset($config['native_api']) || !is_array($config['native_api'])) {
            throw new InvalidArgumentException("native_api configuration required when api_mode is 'native'");
        }

        $nativeApi = $config['native_api'];

        if (!isset($nativeApi['port']) || !is_int($nativeApi['port'])) {
            throw new InvalidArgumentException("native_api.port must be an integer (8728 or 8729)");
        }
        if (!in_array($nativeApi['port'], [8728, 8729])) {
            throw new InvalidArgumentException("native_api.port must be 8728 (plain) or 8729 (TLS)");
        }

        if (!isset($nativeApi['tls']) || !is_bool($nativeApi['tls'])) {
            throw new InvalidArgumentException("native_api.tls must be a boolean");
        }

        // If TLS is enabled, port must be 8729
        if ($nativeApi['tls'] && $nativeApi['port'] !== 8729) {
            throw new InvalidArgumentException("When native_api.tls is true, native_api.port must be 8729");
        }
        // If TLS is disabled, port should be 8728
        if (!$nativeApi['tls'] && $nativeApi['port'] !== 8728) {
            throw new InvalidArgumentException("When native_api.tls is false, native_api.port should be 8728");
        }

        if (!isset($nativeApi['python_script']) || !is_string($nativeApi['python_script']) || $nativeApi['python_script'] === '') {
            throw new InvalidArgumentException("native_api.python_script must be a non-empty string path to the Python bridge script");
        }
        if (!is_file($nativeApi['python_script'])) {
            throw new InvalidArgumentException("native_api.python_script file not found: " . $nativeApi['python_script']);
        }

        // Validate optional host/username/password if provided (they can fall back to main config)
        if (isset($nativeApi['host']) && !is_string($nativeApi['host'])) {
            throw new InvalidArgumentException("native_api.host must be a string");
        }
        if (isset($nativeApi['username']) && !is_string($nativeApi['username'])) {
            throw new InvalidArgumentException("native_api.username must be a string");
        }
        if (isset($nativeApi['password']) && !is_string($nativeApi['password'])) {
            throw new InvalidArgumentException("native_api.password must be a string");
        }
    }

    private static function requireKey(array $config, string $key): void {
        if (!isset($config[$key]) || $config[$key] === '') {
            throw new InvalidArgumentException("Missing required config key: '$key'");
        }
    }

    private static function validateSubnet(string $subnet, string $serverIp): void {
        if (!preg_match(WireGuardManager::SUBNET_REGEX, $subnet, $m)) {
            throw new InvalidArgumentException("Invalid subnet format: '$subnet'. Expected CIDR (e.g. 10.0.0.0/24)");
        }

        $mask = (int)$m[2];
        if ($mask < 1 || $mask > 30) {
            throw new InvalidArgumentException("Subnet mask must be between /1 and /30, got /$mask");
        }

        $networkLong = ip2long($m[1]) & ~((1 << (32 - $mask)) - 1);
        $broadcastLong = $networkLong + (1 << (32 - $mask)) - 1;
        $serverLong = ip2long($serverIp);

        if ($serverLong === false) {
            throw new InvalidArgumentException("Invalid server_ip: '$serverIp'");
        }

        if ($serverLong < $networkLong || $serverLong > $broadcastLong) {
            throw new InvalidArgumentException("server_ip '$serverIp' is outside subnet '$subnet'");
        }

        if ($serverLong === $networkLong) {
            throw new InvalidArgumentException("server_ip '$serverIp' cannot be the network address");
        }

        if ($serverLong === $broadcastLong) {
            throw new InvalidArgumentException("server_ip '$serverIp' cannot be the broadcast address");
        }
    }

    private static function validateEndpoint(string $endpoint): void {
        if (!preg_match('/^[a-zA-Z0-9.\-]+:\d+$/', $endpoint)) {
            throw new InvalidArgumentException("Invalid endpoint format: '$endpoint'. Expected host:port (e.g. vpn.example.com:51820)");
        }

        $parts = explode(':', $endpoint);
        $port = (int)$parts[1];
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException("Endpoint port must be 1-65535, got $port");
        }
    }

    private static function validateClientAllowedIps(string $allowedIps): void {
        foreach (explode(',', $allowedIps) as $cidr) {
            $cidr = trim($cidr);
            if (!preg_match('/^([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)\/([0-9]+)$/', $cidr, $m)) {
                throw new InvalidArgumentException("Invalid CIDR in client_allowed_ips: '$cidr'");
            }
            $mask = (int)$m[2];
            if ($mask < 0 || $mask > 32) {
                throw new InvalidArgumentException("Invalid mask /$mask in client_allowed_ips: '$cidr'");
            }
        }
    }

    private static function validateDnatFormula(array $config): void {
        $base = $config['dnat_base'] ?? 30000;
        $multiplier = $config['dnat_multiplier'] ?? 1000;

        if (!is_int($base) || $base < 1 || $base > 65535) {
            throw new InvalidArgumentException("dnat_base must be integer 1-65535, got $base");
        }
        if (!is_int($multiplier) || $multiplier < 1 || $multiplier > 65535) {
            throw new InvalidArgumentException("dnat_multiplier must be integer 1-65535, got $multiplier");
        }

        if (!isset($config['subnet'])) return;

        if (!preg_match(WireGuardManager::SUBNET_REGEX, $config['subnet'], $m)) return;

        $mask = (int)$m[2];
        
        // Calculate max third octet value based on subnet mask
        // Formula: DNAT_PORT = dnat_base + third_octet * dnat_multiplier + fourth_octet
        if ($mask >= 24) {
            // /24 or smaller: third octet is fixed (part of network), only fourth varies
            $thirdOctetMax = 0;
        } elseif ($mask >= 16) {
            // /16 to /23: third octet varies, fourth varies
            $thirdOctetMax = (1 << (24 - $mask)) - 1;
        } else {
            // /15 or larger: second octet also varies, third can be 0-255
            $thirdOctetMax = 255;
        }
        $maxPort = $base + $thirdOctetMax * $multiplier + 255;
        if ($maxPort > 65535) {
            throw new InvalidArgumentException(
                "DNAT formula overflow: max port would be $maxPort > 65535. " .
                "Reduce dnat_base ($base) or dnat_multiplier ($multiplier) for subnet {$config['subnet']}"
            );
        }
    }

    private static function validateExportMode(array $config): void {
        $mode = $config['export_mode'] ?? 'rsc';
        if (!in_array($mode, ['conf', 'rsc'], true)) {
            throw new InvalidArgumentException("export_mode must be 'conf' or 'rsc', got '$mode'");
        }
    }

    public static function renderErrorPage(string $message): void {
        http_response_code(500);
        $escaped = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration Error</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 700px; margin: 4rem auto; padding: 0 1.5rem; line-height: 1.6; color: #1a1a1a; }
        .error-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 1.5rem; }
        .error-title { color: #dc2626; font-size: 1.25rem; font-weight: 600; margin-bottom: 0.75rem; }
        .error-message { color: #7f1d1d; white-space: pre-wrap; font-family: monospace; font-size: 0.95rem; background: #fff; padding: 1rem; border-radius: 4px; border: 1px solid #fecaca; }
        .hint { margin-top: 1rem; font-size: 0.9rem; color: #6b7280; }
        code { background: #f3f4f6; padding: 0.15rem 0.35rem; border-radius: 3px; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="error-box">
        <div class="error-title">Configuration Error</div>
        <div class="error-message"><?php echo $escaped; ?></div>
        <div class="hint">
            Fix the issue in <code>config.php</code> (copy from <code>config.example.php</code> if needed), then reload.
        </div>
    </div>
</body>
</html>
        <?php
        exit;
    }
}
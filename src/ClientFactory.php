<?php

require_once __DIR__ . '/ClientInterface.php';
require_once __DIR__ . '/MikrotikRestClient.php';
require_once __DIR__ . '/MikrotikApiClient.php';

class ClientFactory
{
    public static function create(array $config): ClientInterface
    {
        $mode = $config['api_mode'] ?? 'rest';
        
        switch ($mode) {
            case 'rest':
                return new MikrotikRestClient(
                    $config['host'],
                    $config['username'],
                    $config['password'],
                    $config['ssl_verify'] ?? false,
                    10,
                    $config['interface'] ?? 'WireGuard-ResNovae'
                );
            
            case 'native':
                if (empty($config['native_api'])) {
                    throw new InvalidArgumentException("native_api configuration required for api_mode='$mode'");
                }
                return new MikrotikApiClient($config, $mode);
            
            default:
                throw new InvalidArgumentException("Unknown api_mode: '$mode'. Supported: rest, native");
        }
    }
}
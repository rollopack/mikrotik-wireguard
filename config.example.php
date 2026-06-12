<?php
/**
 * Configuration Template for MikroTik WireGuard Peer Manager
 *
 * Copy this file to config.php and replace the placeholder values
 * with your actual MikroTik CHR connection details.
 *
 * IMPORTANT: config.php contains sensitive credentials.
 * Never commit it to version control.
 */

return [
    // Application Language (en = English, it = Italian)
    'lang' => 'en',

    // MikroTik CHR Connection Details
    'host' => 'https://192.168.88.1',    // Your MikroTik CHR IP or hostname
    'username' => 'admin',
    'password' => 'YOUR_ROUTER_PASSWORD', // REPLACE with your actual password
    'ssl_verify' => false,    // Set true if using a valid certificate

    // WireGuard Interface and Subnet Settings
    'interface' => 'WireGuard-InterfaceName',
    'subnet' => '10.0.0.0/24',
    'server_ip' => '10.0.0.1',

    // Client Connection Template Settings
    'endpoint' => 'server.example.com:13231',
    'client_allowed_ips' => '10.0.0.0/24,192.168.1.0/24',
];

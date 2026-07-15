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

    // API Mode: 'rest' | 'native'
    // 'rest'    - RouterOS REST API only (port 443), default, no extra deps
    // 'native'  - RouterOS Native API only (port 8728/8729) via Python bridge
    'api_mode' => 'rest',

    // MikroTik CHR Connection Details (used by both REST and Native API)
    'host' => '192.168.88.1',    // Your MikroTik CHR IP or hostname
    'username' => 'admin',
    'password' => 'YOUR_ROUTER_PASSWORD', // REPLACE with your actual password
    'ssl_verify' => false,    // Set true if using a valid certificate

    // Native API Configuration (only used when api_mode is 'native')
    'native_api' => [
        'type' => 'python',              // Bridge type: 'python' (librouteros) or 'ssh' (future)
        'port' => 8728,                  // Native API port: 8728 (plain) or 8729 (TLS)
        'tls' => false,                  // Use TLS (port 8729) if true
        'python_script' => __DIR__ . '/src/get_peer_data.py', // Path to Python bridge
    ],

    // WireGuard Interface and Subnet Settings
    'interface' => 'WireGuard-InterfaceName',
    'subnet' => '10.0.0.0/24',
    'server_ip' => '10.0.0.1',

    // Client Connection Template Settings
    'endpoint' => 'server.example.com:13231',
    'client_allowed_ips' => '10.0.0.0/24,192.168.1.0/24',

    // DNAT Port Mapping (Winbox access behind WireGuard)
    // Formula: dnat_base + third_octet * dnat_multiplier + fourth_octet
    // Adjust these if the default range (30000-65535) doesn't fit your subnet
    'dnat_base' => 30000,
    'dnat_multiplier' => 1000,

    // WireGuard Peer Comment
    // Comment set on the peer connection on the MikroTik.
    // If empty or omitted, falls back to the 'interface' value.
    'comment' => 'ResNovae VPN Server',

    // Dashboard Auto-Refresh Interval (seconds)
    // How often the peer list refreshes. Higher values reduce CHR load.
    // Default: 30
    'refresh_interval' => 30,

    // Handshake Timeout (minutes)
    // Peers with last handshake older than this are shown as offline.
    // Default: 5
    'handshake_timeout' => 5,

    // Peers per page (pagination)
    // Number of peers shown per page in the table. Default: 50
    // Set to 0 to disable pagination (show all peers on one page).
    'page_size' => 50,
];

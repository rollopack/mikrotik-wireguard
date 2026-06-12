# MikroTik WireGuard Peer Manager

A lightweight PHP web dashboard for managing WireGuard peers on a MikroTik RouterOS. Features automatic IP allocation, X25519 key generation, and client configuration export.

## Features

- **Web Dashboard** — List, create, edit, delete WireGuard peers via browser
- **Automatic IP Allocation** — Scans subnet and assigns the next free IP
- **X25519 Key Generation** — Uses `libsodium` for cryptographic key pairs
- **Client Config Export** — Download `.conf` (WireGuard) or `.rsc` (RouterOS script)
- **Key Regeneration** — Rotate keys without deleting/recreating peers
- **Demo Mode** — Fully functional demo using PHP sessions, no router required

## Requirements

- PHP 8.0+ with `ext-sodium` and `ext-json`
- Web server (Apache, Nginx, etc.)
- MikroTik RouterOS 7 with REST API enabled (`/ip/service/set www-ssl`)

## Quick Start

```bash
git clone https://github.com/YOUR_USER/wireguard-manager.git
cd wireguard-manager

cp config.example.php config.php
# Edit config.php with your MikroTik CHR credentials

php tests/run_tests.php

# Open index.php in your browser
# If no router is reachable, Demo Mode activates automatically
```

## Configuration

See `config.example.php` for all available options:

| Key | Description |
|-----|-------------|
| `host` | Router IP or hostname (e.g. `https://192.168.88.1`) |
| `username` | Router username |
| `password` | Router password |
| `ssl_verify` | Verify SSL certificate (`false` for self-signed) |
| `interface` | WireGuard interface name on the router |
| `subnet` | WireGuard subnet in CIDR (e.g. `10.0.0.0/24`) |
| `server_ip` | Server IP inside the subnet |
| `endpoint` | Public endpoint for client connections (e.g. `vpn.example.com:13231`) |
| `client_allowed_ips` | Allowed IPs in generated client configs |

## Security

- **`config.php` is gitignored** — router credentials stay local
- **Demo mode** — activates when password is `password` or `?demo` is in the URL
- **Private keys are never stored** on the server after the modal is closed
- **IP restriction** via `.htaccess` (default: `192.168.111.x`)

## Project Structure

```
├── config.example.php      # Configuration template
├── index.php               # Dashboard UI
├── src/
│   ├── api.php                    # AJAX API endpoints
│   ├── WireGuardManager.php       # Business logic
│   ├── MikrotikRestClient.php     # REST API client (file_get_contents)
│   └── DemoWireGuardManager.php   # Session-based mock for demo mode
├── assets/
│   ├── css/app.css
│   └── js/app.js
└── tests/
    ├── run_tests.php
    └── WireGuardManagerTest.php
```

## Testing

```bash
php tests/run_tests.php
```

Uses a mock REST client — no real router needed. 33+ assertions covering key generation, IP allocation, config formatting, API interaction.

## License

MIT

# MikroTik WireGuard Peer Manager

A lightweight PHP web dashboard for managing WireGuard peers on a MikroTik RouterOS 7 CHR. Features automatic IP allocation, X25519 key generation, client configuration export, and full i18n support (Italian/English).

![Dashboard Screenshot](screenshots/Dashboard.png)
![Export Modal Screenshot](screenshots/Export.png)

> **Disclaimer:** Questo software è fornito "cos com'è", senza alcuna garanzia. L'autore non si assume alcuna responsabilità per danni diretti o indiretti derivanti dall'uso di questo strumento. Usalo a tuo rischio e pericolo.
>
> **This software is provided "as is" without warranty of any kind. The author assumes no responsibility for any direct or indirect damages arising from its use. Use at your own risk.**

> **⚠️ Attenzione / Warning:** Questo strumento è progettato per l'uso in **reti locali fidate**. Se esposto su Internet, è necessario implementare misure di sicurezza aggiuntive come autenticazione HTTP, firewall, reverse proxy con SSL, rate limiting, e monitoraggio degli accessi. Questo script non fornisce protezione intrinseca contro attacchi esterni.
>
> **This tool is designed for use on **trusted local networks**. If exposed to the Internet, additional security measures such as HTTP authentication, firewall, SSL reverse proxy, rate limiting, and access monitoring must be implemented. This script does not provide built-in protection against external attacks.**

## Features

- **Web Dashboard** — List, create, edit, delete WireGuard peers via browser
- **Automatic IP Allocation** — Scans subnet and assigns the next free IP
- **X25519 Key Generation** — Uses `libsodium` for cryptographic key pairs
- **Client Config Export** — Download `.conf` (WireGuard) or `.rsc` (RouterOS script)
- **Key Regeneration** — Rotate keys without deleting/recreating peers
- **DNAT Port Display** — Shows Winbox DNAT port for each peer (formula: `30000 + third_octet * 1000 + fourth_octet`)
- **Internationalization** — Italian and English UI, switchable via `config.php`
- **Demo Mode** — Fully functional demo using PHP sessions, no router required
- **Live Status** — Auto-refresh every 10s, real-time handshake/traffic monitoring

## Requirements

### PHP

| Component | Required | Notes |
|-----------|----------|-------|
| PHP | 8.0+ | |
| `ext-sodium` | Yes | For X25519 key generation (`sodium_crypto_scalarmult_base`) |
| `ext-json` | Yes | For JSON encoding/decoding |
| `ext-mbstring` | Recommended | For multibyte string handling |
| `allow_url_fopen` | `On` | Required by `file_get_contents()` for REST API calls |

### MikroTik RouterOS 7 CHR

| Component | Required | Notes |
|-----------|----------|-------|
| RouterOS | 7.0+ | REST API requires RouterOS 7 |
| REST API | Enabled | `/ip/service/set www-ssl disabled=no port=443` |
| Firewall | Open port 443 | From the dashboard server to the CHR |
| WireGuard | Interface created | e.g. `WireGuard-ResNovae` |
| SSL Certificate | Self-signed OK | Set `ssl_verify: false` in config |

### Web Server (Dashboard Host)

| Component | Notes |
|-----------|-------|
| Apache / Nginx | Any with PHP-FPM |
| PHP | 8.0+ with extensions above |
| Network access | To CHR REST API on port 443 |
| .htaccess support | For IP restriction (optional) |

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
| `lang` | UI language (`it` or `en`) |
| `host` | Router IP or hostname (e.g. `https://192.168.88.1`) |
| `username` | Router username |
| `password` | Router password |
| `ssl_verify` | Verify SSL certificate (`false` for self-signed) |
| `interface` | WireGuard interface name on the router |
| `subnet` | WireGuard subnet in CIDR (e.g. `3.0.0.0/21`) |
| `server_ip` | Server IP inside the subnet |
| `endpoint` | Public endpoint for client connections (e.g. `vpn.example.com:13231`) |
| `client_allowed_ips` | Allowed IPs in generated client configs |

## Security

- **`config.php` is gitignored** — router credentials stay local
- **Demo mode** — activates when password is `password` or `?demo` is in the URL
- **Private keys are never stored** on the server after the modal is closed
- **IP restriction** via `.htaccess` (default: `192.168.111.x`)
- **display_errors disabled** in production — no PHP error leakage
- **Solo reti locali** — non esporre su Internet senza misure aggiuntive
- **Intended for LAN use only** — do not expose to the Internet without additional security layers

## Project Structure

```
├── config.php               # Router credentials (gitignored)
├── config.example.php       # Configuration template
├── index.php                # Dashboard UI
├── Dashboard.png            # Screenshot (root, moved to screenshots/)
├── Export.png               # Screenshot (root, moved to screenshots/)
├── screenshots/
│   ├── Dashboard.png
│   └── Export.png
├── assets/
│   ├── css/
│   │   └── app.css
│   └── js/
│       └── app.js
├── src/
│   ├── api.php                      # AJAX API endpoints
│   ├── WireGuardManager.php         # Business logic
│   ├── MikrotikRestClient.php       # REST API client (file_get_contents)
│   ├── DemoWireGuardManager.php     # Session-based mock for demo mode
│   ├── i18n.php                     # Translation helpers
│   └── lang/
│       ├── it.php                   # Italian strings
│       └── en.php                   # English strings
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

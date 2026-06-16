# MikroTik WireGuard Peer Manager

A lightweight PHP web dashboard for managing WireGuard peers on a MikroTik RouterOS 7 CHR. Features automatic IP allocation, X25519 key generation, client configuration export, and full i18n support (Italian/English).

![Dashboard Screenshot](screenshots/Dashboard.png)
![Export Modal Screenshot](screenshots/Export.png)

## Features

- **Web Dashboard** — List, create, edit, delete WireGuard peers via browser
- **Automatic IP Allocation** — Scans subnet and assigns the next free IP
- **X25519 Key Generation** — Uses `libsodium` for cryptographic key pairs
- **Client Config Export** — Download `.conf` (WireGuard) or `.rsc` (RouterOS script)
- **Key Regeneration** — Rotate keys without deleting/recreating peers
- **Winbox DNAT Port** — Calculate and display the DNAT port for Winbox access to client routers through the CHR (`CHR_IP:DNAT_PORT`). Formula: `dnat_base + third_octet * dnat_multiplier + fourth_octet`, configurable via `config.php`.
- **Internationalization** — Italian and English UI, switchable via `config.php`
- **Live Status** — Auto-refresh every 10s, real-time handshake/traffic monitoring
- **Config Validation** — Startup validation with user-friendly error page for misconfiguration
- **Consistent Error Handling** — All API layers throw exceptions with context

## Requirements

### PHP

### PHP

| Component | Required | Notes |
|-----------|----------|-------|
| PHP | 8.0+ | |
| `ext-sodium` | Yes | For X25519 key generation (`sodium_crypto_scalarmult_base`) |
| `ext-json` | Yes | For JSON encoding/decoding |
| `allow_url_fopen` | `On` | Required by `file_get_contents()` for REST API calls |

### MikroTik RouterOS 7 CHR

| Component | Required | Notes |
|-----------|----------|-------|
| RouterOS | 7.0+ | REST API requires RouterOS 7 |
| REST API | Enabled for `rest` mode | `/ip/service/set www-ssl disabled=no port=443` |
| Native API | Enabled for `native` mode | `/ip/service/set api disabled=no port=8728` |
| Firewall | Open port 443 (rest) or 8728/8729 (native) | From the dashboard server to the CHR |
| WireGuard | Interface created | e.g. `WireGuard-ResNovae` |
| SSL Certificate | Self-signed OK | Set `ssl_verify: false` in config |

### Web Server (Dashboard Host)

| Component | Notes |
|-----------|-------|
| Apache / Nginx | Any with PHP-FPM |
| PHP | 8.0+ with extensions above |
| Python 3.8+ | Required for `native` API mode only |
| `librouteros` | `pip install librouteros` — required for `native` mode only |
| Network access | To CHR on port 443 (rest) or 8728/8729 (native) |
| .htaccess support | For IP restriction (optional) |

## Quick Start

```bash
git clone https://github.com/rollopack/mikrotik-wireguard.git
cd mikrotik-wireguard

cp config.example.php config.php
# Edit config.php with your MikroTik CHR credentials

# Open index.php in your browser
# Connection error banner appears if CHR is unreachable
```

## Configuration

See `config.example.php` for all available options:

| Key | Description |
|-----|-------------|
| `lang` | UI language (`it` or `en`) |
| `api_mode` | API mode: `rest` (default, port 443) or `native` (port 8728/8729) |
| `host` | Router IP or hostname (e.g. `https://192.168.88.1`) |
| `username` | Router username |
| `password` | Router password |
| `ssl_verify` | Verify SSL certificate (`false` for self-signed) |
| `native_api` | Sub-array with `port`, `tls`, `python_script` for native mode |
| `interface` | WireGuard interface name on the router |
| `subnet` | WireGuard subnet in CIDR (e.g. `3.0.0.0/21`) |
| `server_ip` | Server IP inside the subnet |
| `endpoint` | Public endpoint for client connections (e.g. `vpn.example.com:13231`) |
| `client_allowed_ips` | Allowed IPs in generated client configs |
| `dnat_base` | Base port for the DNAT formula (default: `30000`) |
| `dnat_multiplier` | Third octet multiplier in the DNAT formula (default: `1000`) |

## API Modes

The dashboard supports two API modes for connecting to the MikroTik CHR. Set `api_mode` in `config.php`:

| Mode | Description | Requirements |
|------|-------------|--------------|
| `rest` (default) | RouterOS REST API (HTTPS, port 443). Simple, no extra deps. | `allow_url_fopen=On`, PHP 8.0+ |
| `native` | Full RouterOS Native API (port 8728/8729) via Python bridge. Alternative if REST is unavailable. | Python 3.8+, `librouteros`, native API port 8728/8729 open |

### `native` mode

```php
// config.php
'api_mode' => 'native',
'native_api' => [
    'type' => 'python',
    'port' => 8728,                  // 8728 (plain) or 8729 (TLS)
    'tls' => false,                  // true for port 8729 with TLS
    'python_script' => __DIR__ . '/src/get_peer_data.py',
],
```

Credentials (`host`/`username`/`password`) fall back to the main config, so you only need to override them in `native_api` if the native API uses different credentials.

Requirements for `native`:
- Python 3.8+ on the dashboard server
- `pip install librouteros`
- CHR firewall: allow dashboard server IP on port 8728 (or 8729 for TLS)
- `/ip/service/set api disabled=no port=8728` on CHR (and `api-ssl` for TLS)

## DNAT Port Forwarding

This feature calculates a unique DNAT port for each peer so you can reach the client's router via Winbox through the CHR, without exposing the client to the Internet. You need to **manually** create a `dst-nat` rule on the CHR:

```
/ip firewall nat add chain=dstnat action=dst-nat \
    protocol=tcp dst-port=DNAT_PORT \
    to-addresses=CLIENT_WG_IP to-ports=8291
```

The dashboard only *displays* the computed port — it does not manage firewall rules. Clicking a peer's IP badge copies the DNAT port to your clipboard.

The formula is:

```
DNAT_PORT = dnat_base + third_octet * dnat_multiplier + fourth_octet
```

Configure `dnat_base` and `dnat_multiplier` in `config.php` to fit your subnet (see [Configuration](#configuration)).

**Example with defaults (`dnat_base=30000`, `dnat_multiplier=1000`):**  
Peer IP `3.0.0.24` → port `30000 + 0 * 1000 + 24 = **30024**`  
CHR rule: `dst-port=30024` → `to-addresses=3.0.0.24 to-ports=8291`  
Winbox connection: `CHR_IP:30024`

## Security

- **`config.php` is gitignored** — router credentials stay local
- **Private keys are never stored** on the server after the modal is closed
- **IP restriction** via `.htaccess` (default: `192.168.111.x`)
- **display_errors disabled** in production — no PHP error leakage
- **Intended for LAN use only** — do not expose to the Internet without additional security layers

## Project Structure

```
├── .gitignore                # Ignores config.php, temp/, etc.
├── .htaccess                 # IP restriction (192.168.111.x)
├── config.php               # Router credentials (gitignored)
├── config.example.php       # Configuration template
├── index.php                # Dashboard UI
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
│   ├── ClientInterface.php          # Common interface for REST/Native clients
│   ├── ClientFactory.php            # Factory — creates the correct client by api_mode
│   ├── MikrotikRestClient.php       # REST API client (file_get_contents), implements ClientInterface
│   ├── MikrotikApiClient.php        # Native API client (Python bridge), implements ClientInterface
│   ├── get_peer_data.py             # Python bridge: queries RouterOS native API via librouteros
│   ├── WireGuardManager.php         # Business logic
│   ├── ConfigValidator.php          # Configuration validation
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

Uses a mock REST client — no real router needed. 42 assertions covering key generation, IP allocation, config formatting, API interaction, peer CRUD operations (add, update, delete, regenerate key).

> **Disclaimer:** This software is provided "as is" without warranty of any kind. The author assumes no responsibility for any direct or indirect damages arising from its use. Use at your own risk.

> **Warning:** This tool is designed for use on **trusted local networks**. If exposed to the Internet, additional security measures such as HTTP authentication, firewall, SSL reverse proxy, rate limiting, and access monitoring must be implemented. This script does not provide built-in protection against external attacks.

## License

MIT

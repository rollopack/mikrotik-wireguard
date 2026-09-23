# Docker deployment

This directory contains the container build for MikroTik WireGuard Peer Manager.

The image runs the existing PHP application on Apache/PHP 8.3 and exposes HTTP on port `8080`. The same image is used by Docker Compose (single host) and by the Helm chart (Kubernetes).

## Files

```text
build/
├── Dockerfile         # image definition (PHP 8.3 + Apache, optional Python/librouteros)
├── compose.yaml       # single-host Compose deployment (testing / small installs)
├── env.example        # template for build/.env (gitignored, holds RouterOS credentials)
├── entrypoint.sh      # links /data auth files into the document root at startup
├── apache-vhost.conf  # Apache virtual host (listens on 8080)
├── php.ini            # PHP settings (sessions under /data/sessions)
└── README.md          # this guide
```

`build/.env` (copied from `env.example`) is gitignored — never commit it.

## Quickstart (Docker Compose, recommended)

Prerequisites:

- Docker + Docker Compose v2 (`docker compose`)
- Network connectivity from the container to the MikroTik RouterOS management address
- A RouterOS user with sufficient permissions (REST mode normally needs at least `read,write,rest-api`)

Run from the repository root:

```bash
cp build/env.example build/.env
vi build/.env   # fill in at least MIKROTIK_HOST, MIKROTIK_PASSWORD, WIREGUARD_SUBNET, WIREGUARD_SERVER_IP, CLIENT_ENDPOINT, CLIENT_ALLOWED_IPS

docker compose \
  --env-file build/.env \
  -f build/compose.yaml \
  up --build -d
```

Then open:

```text
http://localhost:8080
```

On the first startup, set the dashboard administrator password (`setup.php`) unless authentication was already initialized. Login state survives container recreation (see [Persistent data](#persistent-data)).

## Configuration reference

`compose.yaml` generates the runtime RouterOS configuration (`configs/main.php`) from these environment variables at every container start. All values below come from `build/.env`:

| Variable | Description | Default |
|---|---|---|
| `MIKROTIK_HOST` | RouterOS management IP/hostname reachable from the container | *(required)* |
| `MIKROTIK_USERNAME` | RouterOS API username | *(required)* |
| `MIKROTIK_PASSWORD` | RouterOS API password | *(required)* |
| `MIKROTIK_API_MODE` | `rest` (HTTPS, port 443) or `native` (port 8728/8729 via Python bridge) | `rest` |
| `MIKROTIK_SSL_VERIFY` | Verify RouterOS TLS certificate (`false` for self-signed certs) | `false` |
| `MIKROTIK_INTERFACE` | Existing WireGuard interface name on RouterOS | `wireguard` |
| `WIREGUARD_SUBNET` | Address space used for peer allocation (e.g. `10.0.16.0/24`) | *(required)* |
| `WIREGUARD_SERVER_IP` | RouterOS WireGuard address inside that subnet (not necessarily the LAN address) | *(required)* |
| `CLIENT_ENDPOINT` | Public endpoint in generated client configs (e.g. `terminus.example.org:13231`) | *(required)* |
| `CLIENT_ALLOWED_IPS` | Networks routed through the tunnel (`0.0.0.0/0` for full tunnel) | *(required)* |
| `CLIENT_DNS` | Comma-separated DNS servers for generated configs (e.g. `'10.0.2.254, 1.1.1.1'`) | empty |
| `CLIENT_EXPORT_METADATA` | RouterOS client export metadata for QR/client-config generation; requires RouterOS 7.21+, set `false` for older CHRs | `true` |
| `MIKROTIK_LANG` | UI language (`en` or `it`) | `en` |
| `EXPORT_MODE` | Default export tab after creation/regeneration (`conf` or `rsc`) | `rsc` |
| `REFRESH_INTERVAL` | Dashboard auto-refresh, seconds | `30` |
| `HANDSHAKE_TIMEOUT` | Minutes before a peer shows as offline | `5` |
| `PAGE_SIZE` | Peers per page (`0` disables pagination) | `50` |
| `SHOW_DNAT_COLUMN` / `SHOW_TRAFFIC_COLUMN` | Toggle DNAT Port / Traffic columns | `false` / `true` |
| `DNAT_BASE` / `DNAT_MULTIPLIER` | DNAT port formula parameters | `30000` / `1000` |
| `MIKROTIK_NATIVE_PORT` / `MIKROTIK_NATIVE_TLS` | Native API port / TLS (used only when `MIKROTIK_API_MODE=native`) | `8728` / `false` |
| `MIKROTIK_WIREGUARD_IMAGE` / `HTTP_PORT` | Image tag / published host port | `mikrotik-wireguard:local` / `8080` |

> **Important:** environment variables are fixed at container **creation** time. After editing `build/.env`, recreate the container (`up -d --force-recreate`, must say `Recreated`) — a plain `restart` keeps the old values.

## Lifecycle

All commands run from the repository root.

Check status:

```bash
docker compose \
  --env-file build/.env \
  -f build/compose.yaml \
  ps
```

View logs:

```bash
docker compose \
  --env-file build/.env \
  -f build/compose.yaml \
  logs -f mikrotik-wireguard
```

Inspect the effective configuration inside the running container (contains the RouterOS username and password — do not paste it anywhere):

```bash
docker compose \
  --env-file build/.env \
  -f build/compose.yaml \
  exec mikrotik-wireguard \
  cat /var/www/html/configs/main.php
```

Test RouterOS REST connectivity from inside the container (a `401 Unauthorized` still confirms TCP + TLS work):

```bash
docker compose \
  --env-file build/.env \
  -f build/compose.yaml \
  exec mikrotik-wireguard \
  curl -vk https://10.0.2.254/rest/system/resource
```

Replace `10.0.2.254` with your configured RouterOS host.

Rebuild after PHP/JS/CSS source changes (the `Dockerfile` copies sources at build time):

```bash
docker compose \
  --env-file build/.env \
  -f build/compose.yaml \
  build --no-cache

docker compose \
  --env-file build/.env \
  -f build/compose.yaml \
  up -d --force-recreate
```

Stop (removes container and network, preserves the data volume):

```bash
docker compose \
  --env-file build/.env \
  -f build/compose.yaml \
  down
```

Reset all local state (fresh install on next startup):

```bash
docker compose \
  --env-file build/.env \
  -f build/compose.yaml \
  down -v
```

## Persistent data

The Compose configuration mounts a Docker volume at `/data`, holding runtime state:

```text
/data/.admin-hash
/data/.api-token
/data/sessions/
```

This data survives normal container recreation. The entrypoint symlinks `.admin-hash` and `.api-token` back into the document root; the symlinks are owned by `www-data` so Linux protected-symlink settings do not block Apache/PHP.

## Plain `docker run` (advanced)

Compose (above) is the recommended path because it generates the router configuration. For a manual run you must provide both the root loader and at least one server config yourself:

```bash
docker build \
  -f build/Dockerfile \
  -t mikrotik-wireguard:latest \
  .

mkdir -p ./data ./my-configs
# put a valid server file (see configs/config.php.dist) at ./my-configs/main.php
# and a copy of the root loader at ./config-loader.php:
#   <?php require_once __DIR__ . '/src/ConfigManager.php'; return ConfigManager::resolveConfig();

docker run --rm \
  --name mikrotik-wireguard \
  -p 8080:8080 \
  -v "$(pwd)/data:/data" \
  -v "$(pwd)/config-loader.php:/var/www/html/config.php:ro" \
  -v "$(pwd)/my-configs:/var/www/html/configs:ro" \
  mikrotik-wireguard:latest
```

The Docker build context must be the repository root so the application source can be copied into the image.

## Image registry

```bash
docker build \
  -f build/Dockerfile \
  -t mikrotik-wireguard:latest \
  .

docker tag mikrotik-wireguard:latest registry.domain.tld/mikrotik-wireguard:TAG
docker push registry.domain.tld/mikrotik-wireguard:TAG
```

Use immutable image tags for production deployments instead of relying only on `latest`.

## TLS

When using RouterOS REST over HTTPS, certificate verification should preferably remain enabled.

If RouterOS uses a self-signed certificate during initial testing, disable verification via `MIKROTIK_SSL_VERIFY=false`. For production, use a certificate trusted by the container.

## Security notes

- `build/.env` contains RouterOS credentials and must not be committed to Git.
- For local testing, credentials come from environment variables. Kubernetes deployments should use Kubernetes Secrets as documented by the Helm chart.
- Do not expose the local testing service directly to the public Internet.

## Helm

For Kubernetes deployments, use the Helm chart in `helm-chart/` — see `helm-chart/README.md`.

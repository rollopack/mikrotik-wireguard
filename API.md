# Public API

Machine-to-machine endpoint for creating WireGuard peers programmatically (e.g. from another tool that generates client configs). It returns the assigned IP, the key pair and the ready-to-use `.conf`/`.rsc`.

## Setup

```bash
# Generate a token (one-time)
openssl rand -hex 32 > .api-token
chmod 600 .api-token
```

- The token file is gitignored and must stay owner-only (`chmod 600`). The Docker/Compose and Helm deployments set this automatically; the programmatic writer `saveApiToken()` in `src/auth.php` also forces `0600`.
- **Required**: make sure the web server blocks dotfiles (add the `<FilesMatch "^\.">` block from `.htaccess.example` to your live `.htaccess`), otherwise the token is downloadable. Verify with `https://your-host/.api-token` → must return **403**.
- If a token is leaked, rotate it: overwrite `.api-token` with a new value.

## Endpoint

```
POST https://your-host/src/public-api.php?action=create_peer[&server=key]
Authorization: Bearer <token>
Content-Type: application/json

{"name": "peer-name"}
```

```
GET https://your-host/src/public-api.php?action=check_peer&name=peer-name[&server=key]
Authorization: Bearer <token>
```

```
POST https://your-host/src/public-api.php?action=regenerate_peer[&server=key]
Authorization: Bearer <token>
Content-Type: application/json

{"name": "peer-name"}
```

```
POST https://your-host/src/public-api.php?action=delete_peer[&server=key]
Authorization: Bearer <token>
Content-Type: application/json

{"name": "peer-name"}
```

```
POST https://your-host/src/public-api.php?action=toggle_peer[&server=key]
Authorization: Bearer <token>
Content-Type: application/json

{"name": "peer-name", "disabled": true}
```

- `server` is optional — omit it to use the default (first) server config. A `server` value that doesn't match any config returns HTTP `400` with `"Unknown server"` (no silent fallback).
- `name` is the peer comment/name on the router; it must be unique (for `create_peer`).

## Actions

| Action | Method | Input | Purpose |
|--------|--------|-------|---------|
| `create_peer` | `POST` | JSON `{name}` | Create a new peer, returns IP + key pair + config/script |
| `check_peer` | `GET` | `?name=` | Check if a peer name already exists, returns its current IP |
| `regenerate_peer` | `POST` | JSON `{name}` | Regenerate the key pair of an existing peer (same name/IP, new keys) |
| `delete_peer` | `POST` | JSON `{name}` | Delete a peer by name (irreversible) |
| `toggle_peer` | `POST` | JSON `{name, disabled}` | Enable/disable a peer without touching its config or keys (`disabled: true/false`) |

`check_peer` + `regenerate_peer` implement the "exists? ask to overwrite" flow: the caller first checks the name, then either creates or regenerates based on the user's choice. `delete_peer` covers client removal; `toggle_peer` covers reversible suspension (e.g. non-paying client).

## Response

### Success (`200`)

```json
{
  "success": true,
  "peer": {
    ".id": "*1c",
    "name": "peer-name",
    "ip": "3.0.0.2",
    "public_key": "...",
    "private_key": "...",
    "config": "[Interface]\nPrivateKey = ...\n...",
    "script": "# --- MikroTik Client Setup Script ---\n..."
  }
}
```

- `peer.config` is a ready-to-use WireGuard `.conf` for the client.
- `peer.script` is a MikroTik `.rsc` script for the client router.

### `check_peer` success (`200`) — `exists` is `false` when the name is free:

```json
{
  "success": true,
  "exists": true,
  "peer": {"name": "peer-name", "ip": "3.0.0.2"}
}
```

### `regenerate_peer` success (`200`) — same shape as `create_peer`, with the **unchanged IP** and a fresh key pair:

```json
{
  "success": true,
  "peer": {".id": "*1c", "name": "peer-name", "ip": "3.0.0.2", "public_key": "...", "private_key": "...", "config": "...", "script": "..."}
}
```

### `delete_peer` success (`200`):

```json
{
  "success": true,
  "deleted": {"name": "peer-name", "ip": "3.0.0.2"}
}
```

### `toggle_peer` success (`200`) — `disabled` reflects the applied state:

```json
{
  "success": true,
  "peer": {"name": "peer-name", "ip": "3.0.0.2", "disabled": true}
}
```

### Errors

| HTTP | Body | Meaning |
|------|------|---------|
| `401` | `{"success": false, "error": "Unauthorized"}` | Missing/invalid token |
| `400` | `{"success": false, "error": "Unknown server: X"}` | `server` value doesn't match any config |
| `400` | `{"success": false, "error": "Invalid disabled value."}` | `disabled` must be `true`/`false`/`1`/`0` (`toggle_peer`) |
| `200` | `{"success": false, "error": "Peer name cannot be empty."}` | Missing `name` |
| `200` | `{"success": false, "error": "...already exists."}` | Duplicate peer name (`create_peer`) |
| `200` | `{"success": false, "error": "...was not found."}` | Unknown peer name (`regenerate_peer`, `delete_peer`, `toggle_peer`) |
| `200` | `{"success": false, "error": "Invalid JSON body"}` | Malformed body |
| `200` | `{"success": false, "error": "..."}` | Router/API error (e.g. subnet full) |

Always check the `success` field. The private key is returned only once, in the response.

## Example (curl) — create with "overwrite?" flow

```bash
# 1) Check if the name already exists
curl -sS -H "Authorization: Bearer $(cat .api-token)" \
  "https://your-host/src/public-api.php?action=check_peer&name=cliente-xyz"
# {"success":true,"exists":false,"peer":null}  -> create
# {"success":true,"exists":true,"peer":{"name":"cliente-xyz","ip":"3.0.0.2"}} -> ask to overwrite

# 2a) Create a new peer
curl -sS -X POST \
  -H "Authorization: Bearer $(cat .api-token)" \
  -H "Content-Type: application/json" \
  -d '{"name":"cliente-xyz"}' \
  https://your-host/src/public-api.php?action=create_peer

# 2b) Overwrite: regenerate keys of the existing peer (same IP)
curl -sS -X POST \
  -H "Authorization: Bearer $(cat .api-token)" \
  -H "Content-Type: application/json" \
  -d '{"name":"cliente-xyz"}' \
  https://your-host/src/public-api.php?action=regenerate_peer
```

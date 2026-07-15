#!/usr/bin/env python3
"""
Python bridge script to query RouterOS native API (port 8728/8729) via librouteros.
Returns peer data including last-handshake for all peers (including offline ones).

Input (stdin JSON):
{
    "host": "192.168.88.1",
    "port": 8728,
    "username": "admin",
    "password": "secret",
    "action": "get_peers",          # get_peers | get_all_peers | get_interface | add_peer | update_peer | delete_peer | get_ppp_secrets | get_ppp_active
    "peers": ["peer1", "peer2"],    # optional: specific peer names to query
    "interface": "WireGuard-ResNovae",   # optional: filter by interface
    "tls": false,                         # optional: use TLS (port 8729)
    # For add_peer:
    "payload": {                          # peer data: interface, public-key, allowed-address, name
        "interface": "WireGuard-ResNovae",
        "public-key": "base64key",
        "allowed-address": "10.0.0.2/32",
        "name": "new-peer"
    },
    # For update_peer:
    "peer_id": "*1c",                     # peer ID to update
    "update_data": {                      # fields to update
        "name": "updated-name"
    },
    # For delete_peer:
    "peer_id": "*1c"                      # peer ID to delete
}

Output (stdout JSON):
{
    "peer1": {
        "last-handshake": "1h30m15s",
        "current-endpoint-address": "1.2.3.4",
        "rx": "1024",
        "tx": "2048"
    },
    ...
}
OR for add_peer:
{
    ".id": "*1d",
    "name": "new-peer",
    "public-key": "base64key",
    "allowed-address": "10.0.0.2/32",
    "interface": "WireGuard-ResNovae"
}
"""

import sys
import json
import os

def main():
    try:
        # Read input from stdin
        input_data = sys.stdin.read().strip()
        if not input_data:
            print(json.dumps({"error": "No input data received"}))
            sys.exit(1)
        
        config = json.loads(input_data)
        
        # Validate required fields
        required = ['host', 'username', 'password']
        for field in required:
            if field not in config:
                print(json.dumps({"error": f"Missing required field: {field}"}))
                sys.exit(1)
        
        host = config['host']
        username = config['username']
        password = config['password']
        port = config.get('port', 8728)
        peer_names = config.get('peers', [])
        interface = config.get('interface', '')
        use_tls = config.get('tls', False)
        action = config.get('action', 'get_peers')
        
        # Import librouteros
        try:
            from librouteros import connect
            from librouteros.query import Key
        except ImportError as e:
            print(json.dumps({"error": f"librouteros not installed: {e}"}), file=sys.stderr)
            sys.exit(1)
        
        # Connect to RouterOS native API with timeout
        try:
            connect_kwargs = {
                'username': username,
                'password': password,
                'host': host,
                'port': port if port != 8728 or use_tls else 8728,
                'timeout': 10,  # Socket timeout in seconds
            }
            if use_tls:
                connect_kwargs['port'] = 8729 if port == 8728 else port
                connect_kwargs['ssl'] = True
            
            api = connect(**connect_kwargs)
        except Exception as e:
            print(json.dumps({"error": f"Connection failed: {e}"}), file=sys.stderr)
            sys.exit(1)
        
        result = {}
        
        try:
            peer_fields = [
                Key('.id'),
                Key('name'),
                Key('last-handshake'),
                Key('current-endpoint-address'),
                Key('rx'),
                Key('tx'),
                Key('allowed-address'),
                Key('interface'),
                Key('public-key'),
                Key('disabled'),
            ]
            iface_fields = [
                Key('name'),
                Key('public-key'),
                Key('running'),
                Key('disabled'),
                Key('listen-port'),
                Key('mtu'),
                Key('comment'),
            ]
            
            if action == 'get_peers':
                # Query specific peers by name (if provided) or all peers on interface
                if peer_names:
                    for name in peer_names:
                        query = api.path('/interface/wireguard/peers').select(*peer_fields).where(
                            Key('name') == name
                        )
                        peers = list(query)
                        if peers:
                            p = peers[0]
                            result[name] = {
                                '.id': p.get('.id', ''),
                                'last-handshake': p.get('last-handshake', ''),
                                'current-endpoint-address': p.get('current-endpoint-address', ''),
                                'rx': p.get('rx', '0'),
                                'tx': p.get('tx', '0'),
                                'allowed-address': p.get('allowed-address', ''),
                                'interface': p.get('interface', ''),
                                'public-key': p.get('public-key', ''),
                            }
                        else:
                            result[name] = {
                                '.id': '',
                                'last-handshake': '',
                                'current-endpoint-address': '',
                                'rx': '0',
                                'tx': '0',
                                'allowed-address': '',
                                'interface': '',
                                'public-key': '',
                            }
                else:
                    # Query all peers on interface
                    if interface:
                        query = api.path('/interface/wireguard/peers').select(*peer_fields).where(
                            Key('interface') == interface
                        )
                    else:
                        query = api.path('/interface/wireguard/peers').select(*peer_fields)
                    peers = list(query)
                    for p in peers:
                        name = p.get('name', p.get('.id', ''))
                        if name:
                            result[name] = {
                                '.id': p.get('.id', ''),
                                'last-handshake': p.get('last-handshake', ''),
                                'current-endpoint-address': p.get('current-endpoint-address', ''),
                                'rx': p.get('rx', '0'),
                                'tx': p.get('tx', '0'),
                                'allowed-address': p.get('allowed-address', ''),
                                'interface': p.get('interface', ''),
                                'public-key': p.get('public-key', ''),
                                'disabled': p.get('disabled', 'no'),
                            }
                
                # Also get WireGuard interface public key
                if interface:
                    iface_query = api.path('/interface/wireguard').select(*iface_fields).where(
                        Key('name') == interface
                    )
                    interfaces = list(iface_query)
                    if interfaces:
                        iface = interfaces[0]
                        result[interface] = {
                            'public-key': iface.get('public-key', ''),
                        }
            
            elif action == 'get_all_peers':
                # Query all peers without interface filter
                query = api.path('/interface/wireguard/peers').select(*peer_fields)
                peers = list(query)
                for p in peers:
                    name = p.get('name', p.get('.id', ''))
                    if name:
                        result[name] = {
                            '.id': p.get('.id', ''),
                            'last-handshake': p.get('last-handshake', ''),
                            'current-endpoint-address': p.get('current-endpoint-address', ''),
                            'rx': p.get('rx', '0'),
                            'tx': p.get('tx', '0'),
                            'allowed-address': p.get('allowed-address', ''),
                            'interface': p.get('interface', ''),
                            'public-key': p.get('public-key', ''),
                            'disabled': p.get('disabled', 'no'),
                        }
            
            elif action == 'get_interface':
                # Get interface status details
                if not interface:
                    print(json.dumps({"error": "Interface name required for get_interface"}))
                    sys.exit(1)
                iface_query = api.path('/interface/wireguard').select(*iface_fields).where(
                    Key('name') == interface
                )
                interfaces = list(iface_query)
                if interfaces:
                    iface = interfaces[0]
                    result[interface] = {
                        'name': iface.get('name', ''),
                        'public-key': iface.get('public-key', ''),
                        'running': iface.get('running', 'false'),
                        'disabled': iface.get('disabled', 'false'),
                        'listen-port': iface.get('listen-port', '0'),
                        'mtu': iface.get('mtu', '0'),
                        'comment': iface.get('comment', ''),
                    }
                else:
                    print(json.dumps({"error": f"Interface '{interface}' not found"}))
                    sys.exit(1)
            
            elif action == 'add_peer':
                payload = config.get('payload', {})
                required_fields = ['interface', 'public-key', 'allowed-address', 'name']
                for field in required_fields:
                    if field not in payload:
                        print(json.dumps({"error": f"Missing required payload field: {field}"}))
                        sys.exit(1)
                
                # Add peer via librouteros
                peer_path = api.path('/interface/wireguard/peers')
                new_peer_id = peer_path.add(
                    interface=payload['interface'],
                    **{'public-key': payload['public-key']},
                    **{'allowed-address': payload['allowed-address']},
                    name=payload['name']
                )
                
                result = {
                    '.id': new_peer_id,
                    'name': payload['name'],
                    'public-key': payload['public-key'],
                    'allowed-address': payload['allowed-address'],
                    'interface': payload['interface']
                }
            
            elif action == 'update_peer':
                peer_id = config.get('peer_id', '')
                update_data = config.get('update_data', {})
                
                if not peer_id:
                    print(json.dumps({"error": "peer_id required for update_peer"}))
                    sys.exit(1)
                
                # Update peer via librouteros - path().update() passes .id in kwargs
                try:
                    api.path('/interface/wireguard/peers').update(**{'.id': peer_id, **update_data})
                except Exception as e:
                    print(json.dumps({"error": f"Update failed: {e}"}))
                    sys.exit(1)
                
                # Fetch updated peer to return current state
                query = api.path('/interface/wireguard/peers').select(*peer_fields).where(Key('.id') == peer_id)
                peers = list(query)
                if peers:
                    p = peers[0]
                    result = {
                        '.id': p.get('.id', peer_id),
                        'name': p.get('name', ''),
                        'public-key': p.get('public-key', ''),
                        'allowed-address': p.get('allowed-address', ''),
                        'interface': p.get('interface', '')
                    }
                else:
                    # Fallback
                    result = {
                        '.id': peer_id,
                        'name': update_data.get('name', ''),
                        'public-key': '',
                        'allowed-address': '',
                        'interface': ''
                    }
            
            elif action == 'delete_peer':
                peer_id = config.get('peer_id', '')
                
                if not peer_id:
                    print(json.dumps({"error": "peer_id required for delete_peer"}))
                    sys.exit(1)
                
                # Delete peer via librouteros - path().remove(id)
                try:
                    api.path('/interface/wireguard/peers').remove(peer_id)
                except Exception as e:
                    print(json.dumps({"error": f"Delete failed: {e}"}))
                    sys.exit(1)
                
                result = {'success': True, 'deleted_id': peer_id}
            
            elif action == 'get_ppp_secrets':
                secret_fields = [
                    Key('name'),
                    Key('service'),
                    Key('disabled'),
                    Key('remote-address'),
                    Key('address'),
                ]
                query = api.path('/ppp/secret').select(*secret_fields)
                secrets = list(query)
                for s in secrets:
                    name = s.get('name', s.get('.id', ''))
                    if name:
                        result[name] = {
                            'service': s.get('service', ''),
                            'disabled': s.get('disabled', 'no'),
                            'remote-address': s.get('remote-address', ''),
                            'address': s.get('address', ''),
                        }
            
            elif action == 'get_ppp_active':
                active_fields = [
                    Key('name'),
                    Key('service'),
                    Key('address'),
                    Key('remote-address'),
                ]
                query = api.path('/ppp/active').select(*active_fields)
                active = list(query)
                for a in active:
                    name = a.get('name', a.get('.id', ''))
                    if name:
                        result[name] = {
                            'service': a.get('service', ''),
                            'address': a.get('address', ''),
                            'remote-address': a.get('remote-address', ''),
                        }
            
            else:
                print(json.dumps({"error": f"Unknown action: {action}"}))
                sys.exit(1)
        
        finally:
            api.close()
        
        # Output result
        print(json.dumps(result))
        
    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)


if __name__ == '__main__':
    main()

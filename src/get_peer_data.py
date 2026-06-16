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
    "peers": ["peer1", "peer2"],        # optional: specific peer names to query
    "interface": "WireGuard-ResNovae",   # optional: filter by interface
    "tls": false                         # optional: use TLS (port 8729)
}

Output (stdout JSON):
{
    "peer1": {
        "last-handshake": "1h30m15s",
        "current-endpoint-address": "1.2.3.4",
        "rx": "1024",
        "tx": "2048"
    },
    "peer2": {
        "last-handshake": "",
        "current-endpoint-address": "",
        "rx": "0",
        "tx": "0"
    }
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
                Key('name'),
                Key('last-handshake'),
                Key('current-endpoint-address'),
                Key('rx'),
                Key('tx'),
                Key('allowed-address'),
            ]
            iface_fields = [
                Key('name'),
                Key('public-key'),
            ]
            
            # Get peers using correct librouteros API (path().select().where())
            if peer_names:
                # Query specific peers by name
                for name in peer_names:
                    query = api.path('/interface/wireguard/peers').select(*peer_fields).where(
                        Key('name') == name
                    )
                    peers = list(query)
                    if peers:
                        p = peers[0]
                        result[name] = {
                            'last-handshake': p.get('last-handshake', ''),
                            'current-endpoint-address': p.get('current-endpoint-address', ''),
                            'rx': p.get('rx', '0'),
                            'tx': p.get('tx', '0'),
                            'allowed-address': p.get('allowed-address', ''),
                        }
                    else:
                        result[name] = {
                            'last-handshake': '',
                            'current-endpoint-address': '',
                            'rx': '0',
                            'tx': '0',
                            'allowed-address': '',
                        }
            else:
                # Query all peers
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
                            'last-handshake': p.get('last-handshake', ''),
                            'current-endpoint-address': p.get('current-endpoint-address', ''),
                            'rx': p.get('rx', '0'),
                            'tx': p.get('tx', '0'),
                            'allowed-address': p.get('allowed-address', ''),
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
        
        finally:
            api.close()
        
        # Output result
        print(json.dumps(result))
        
    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)


if __name__ == '__main__':
    main()

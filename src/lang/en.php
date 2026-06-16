<?php

return [

    // ── HTML <head> ────────────────────────────────────────────
    'site.title'             => 'WireGuard Peer Manager - ResNovae',
    'site.description'       => 'Web dashboard for WireGuard peers on MikroTik RouterOS 7.',

    // ── Banners ────────────────────────────────────────────────
    'banner.api_error'       => 'API connection error: <strong>%s</strong>.',

    // ── Header ─────────────────────────────────────────────────
    'header.title'           => 'WireGuard Peer Manager',
    'header.subtitle'        => 'WireGuard Administration for MikroTik RouterOS 7',
    'header.router_chr'      => 'CHR Router:',
    'header.api_mode'        => 'API Mode:',
    'header.api_rest'        => 'REST (port 443)',
    'header.api_native'      => 'Native (port %s)',

    // ── Stats ──────────────────────────────────────────────────
    'stats.total_peers'      => 'Total Peers',
    'stats.total_peers_desc' => 'Configured on the interface',
    'stats.subnet'           => 'WireGuard Subnet',
    'stats.subnet_prefix'    => 'Server IP:',
    'stats.endpoint'         => 'Public Endpoint',
    'stats.endpoint_desc'    => 'Address for client connection',
    'stats.active_peers'     => 'Active Peers',
    'stats.active_peers_desc'=> 'Handshake completed < 5 min',

    // ── Search / Toolbar ───────────────────────────────────────
    'search.placeholder'     => 'Search peer by name or IP address...',
    'search.hide_offline_title' => 'Hide offline peers',
    'search.hide_offline'    => 'Hide offline',
    'search.show_all'        => 'Show all',
    'search.add_peer'        => 'Add New Peer',

    // ── Table ──────────────────────────────────────────────────
    'table.name'             => 'Name',
    'table.ip'               => 'Assigned IP',
    'table.handshake'        => 'Last Handshake',
    'table.endpoint'         => 'Endpoint',
    'table.traffic'          => 'Traffic',
    'table.actions'          => 'Actions',

    // ── Empty state ────────────────────────────────────────────
    'empty.title'            => 'No peers found',
    'empty.description'      => 'No clients configured on this WireGuard interface.',

    // ── Add Peer Modal ─────────────────────────────────────────
    'modal.add.title'            => 'Add New WireGuard Peer',
    'modal.add.description'      => 'Client cryptographic keys will be automatically generated and the first available IP address within the subnet will be assigned.',
    'modal.add.label_name'       => 'Peer / Client Name',
    'modal.add.placeholder_name' => 'e.g. My-Office',
    'modal.add.success_title'    => 'Peer Created Successfully',
    'modal.add.success_desc'     => 'Save the details or download the configuration file now. For security reasons, the private key will no longer be visible once this window is closed.',
    'modal.add.label_ip'         => 'Assigned IP Address',
    'modal.add.tab_conf'         => 'Configuration (.conf)',
    'modal.add.tab_script'       => 'RouterOS Script (.rsc)',
    'modal.add.copy_title'       => 'Copy code',
    'modal.add.download_conf'    => 'Download .conf file',
    'modal.add.download_script'  => 'Download .rsc script',
    'modal.add.cancel'           => 'Cancel',
    'modal.add.submit'           => 'Create Peer',

    // ── Edit Peer Modal ────────────────────────────────────────
    'modal.edit.title'       => 'Edit Peer Name',
    'modal.edit.label_name'  => 'Peer Name / Comment',
    'modal.edit.cancel'      => 'Cancel',
    'modal.edit.submit'      => 'Save Changes',

    // ── Delete Peer Modal ──────────────────────────────────────
    'modal.delete.title'       => 'Delete WireGuard Peer',
    'modal.delete.confirm'     => 'Are you sure you want to delete peer <strong>%s</strong>?',
    'modal.delete.description' => 'This action is irreversible and will remove the client\'s server access configuration.',
    'modal.delete.cancel'      => 'Cancel',
    'modal.delete.submit'      => 'Delete Now',

    // ── Export Modal ───────────────────────────────────────────
    'modal.export.title'           => 'Export Client Configuration',
    'modal.export.label_ip'        => 'IP Address',
    'modal.export.label_port'      => 'Winbox Port (DNAT)',
    'modal.export.regenerate_btn'  => 'Regenerate Key & Download Config',
    'modal.export.regenerate_desc' => 'Generate a new private key, update the peer on the CHR, and download the complete configuration.',
    'modal.export.tab_conf'        => 'Configuration (.conf)',
    'modal.export.tab_script'      => 'RouterOS Script (.rsc)',
    'modal.export.download_conf'   => 'Download .conf file',
    'modal.export.download_script' => 'Download .rsc script',
    'modal.export.close'           => 'Close',

    // ── Toast ──────────────────────────────────────────────────
    'toast.default'          => 'Operation completed successfully!',

    // ── API error messages ─────────────────────────────────────
    'api.action_required'    => 'Action not specified.',
    'api.name_required'      => 'Peer name cannot be empty.',
    'api.id_required'        => 'ID is required.',
    'api.id_name_required'   => 'ID and name are required.',
    'api.unknown_action'     => 'Unknown action: %s',

    // ── JavaScript UI strings ──────────────────────────────────
    'js.col_name'             => 'Name & Comment',
    'js.col_ip'               => 'Assigned IP',
    'js.col_handshake'        => 'Last Handshake',
    'js.col_endpoint'         => 'Endpoint',
    'js.col_traffic'          => 'Traffic',
    'js.col_actions'          => 'Actions',
    'js.regenerate_btn'       => 'Regenerate Key & Download Config',
    'js.script_comment_header' => 'Paste this code in your MikroTik terminal',
    'js.regenerate_error'     => 'Error: %s',
    'js.dnat_copied'          => 'Winbox Port (DNAT): %s copied!',
    'js.code_copy_failed'     => 'Unable to copy code automatically.',
    'js.hide_offline'         => 'Hide offline',
    'js.show_all'             => 'Show all',
    'js.load_error'           => 'Error loading data: %s',
    'js.connection_error'     => 'Backend server connection error.',
    'js.endpoint_na'          => 'Not Connected',
    'js.unnamed'              => 'Unnamed',
    'js.copy_port_title'      => 'Copy Winbox Port (DNAT)',
    'js.rx_label'             => '↓ rx',
    'js.tx_label'             => '↑ tx',
    'js.download_title'       => 'Download client configuration',
    'js.edit_title'           => 'Edit Name',
    'js.delete_title'         => 'Delete peer',
    'js.creating'             => 'Creating...',
    'js.peer_created'         => 'WireGuard peer added successfully!',
    'js.create_error'         => 'Error during creation: %s',
    'js.api_error'            => 'API connection error.',
    'js.file_downloaded'      => 'File %s downloaded!',
    'js.peer_updated'         => 'Peer name updated successfully!',
    'js.update_error'         => 'Error during update: %s',
    'js.peer_deleted'         => 'WireGuard peer removed successfully!',
    'js.delete_error'         => 'Error during deletion: %s',
    'js.regenerate_confirm'   => 'WARNING: Key regeneration will immediately interrupt the VPN on this client until you import the new configuration on the router. Proceed?',
    'js.regenerating'         => 'Regenerating...',
    'js.key_regenerated'      => 'Key regenerated successfully!',
    'js.config_updated'       => 'Configuration Updated',
    'js.error_prefix'         => 'Error: %s',
    'js.port_copied'          => 'Winbox Port (DNAT): %s copied!',
    'js.copy_failed'          => 'Unable to copy.',
    'js.code_copied'          => 'Code copied to clipboard!',
    'js.copy_auto_failed'     => 'Unable to copy code automatically.',
];

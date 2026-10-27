<?php

return [

    // ── HTML <head> ────────────────────────────────────────────
    'site.title'             => 'WireGuard Peer Manager - ResNovae',
    'site.description'       => 'Web dashboard for WireGuard peers on MikroTik RouterOS 7.',

    // ── Banners ────────────────────────────────────────────────

    // ── Header ─────────────────────────────────────────────────
    'header.title'           => 'WireGuard Peer Manager',
    'header.subtitle'        => 'WireGuard Administration for MikroTik RouterOS 7',
    'header.router_chr'      => 'CHR Router:',
    'header.api_mode'        => 'API Mode:',
    'header.api_rest'        => 'REST (port 443)',
    'header.api_native'      => 'Native (port %s)',
    'header.server_select'   => 'Select server',
    'header.about'           => 'About',

    // ── About ──────────────────────────────────────────────────
    'about.title'            => 'About',
    'about.version'          => 'Version',
    'about.repository'       => 'GitHub repository',
    'about.readme_unavailable' => 'README not available.',

    // ── Stats ──────────────────────────────────────────────────
    'stats.total_peers'      => 'Total Peers',
    'stats.total_peers_desc' => 'Configured on the interface',
    'stats.subnet'           => 'WireGuard Subnet',
    'stats.subnet_prefix'    => 'Server IP:',
    'stats.endpoint'         => 'Public Endpoint',
    'stats.endpoint_desc'    => 'Address for client connection',
    'stats.active_peers'     => 'Active Peers',
    'stats.active_peers_desc'=> 'Handshake completed within %d min',


    // ── Search / Toolbar ───────────────────────────────────────
    'search.placeholder'     => 'Search peer by name or IP address...',
    'search.hide_offline_title' => 'Hide offline peers',
    'search.add_peer'        => 'Add New Peer',

    // ── Table ──────────────────────────────────────────────────
    'table.name'        => 'Name',
    'table.aria_label'  => 'WireGuard peers list',
    'table.ip'               => 'Assigned IP',
    'table.handshake'        => 'Handshake',
    'table.endpoint'         => 'Endpoint',
    'table.traffic'          => 'Traffic',
    'table.dnat_port'        => 'DNAT Port',
    'table.actions'          => 'Actions',

    // ── Empty state (filled by JS) ─────────────────────────────

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
    'modal.add.tab_qrcode'       => 'QR Code',
    'modal.add.copy_title'       => 'Copy code',
    'modal.close'                => 'Close',
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

    'modal.confirm.title'  => 'Confirm',
    'modal.confirm.cancel' => 'Cancel',
    'modal.confirm.submit' => 'Continue',

    // ── Export Modal ───────────────────────────────────────────
    'modal.export.title'           => 'Export Client Configuration',
    'modal.export.label_ip'        => 'IP Address',
    'modal.export.label_port'      => 'Winbox Port (DNAT)',
    'modal.export.regenerate_btn'  => 'Regenerate Key & Download Config',
    'modal.export.regenerate_desc' => 'Generate a new private key, update the peer on the CHR, and download the complete configuration.',
    'modal.export.tab_conf'        => 'Configuration (.conf)',
    'modal.export.tab_script'      => 'RouterOS Script (.rsc)',
    'modal.export.tab_qrcode'      => 'QR Code',
    'modal.export.download_conf'   => 'Download .conf file',
    'modal.export.download_script' => 'Download .rsc script',
    'modal.export.close'           => 'Close',
    'modal.qrcode.description'     => 'Scan this code with the WireGuard mobile app.',

    // ── Export VPN IPs Modal ───────────────────────────────────
    'modal.export_vpn.title'         => 'Export VPN IPs',
    'modal.export_vpn.description'   => 'Export WireGuard peer IPs (and optionally SSTP user IPs) to a text file.',
    'modal.export_vpn.include_sstp'  => 'Include SSTP secret IPs',
    'modal.export_vpn.include_pptp'  => 'Include PPTP secret IPs',
    'modal.export_vpn.download'      => 'Download',
    'modal.export_vpn.cancel'        => 'Cancel',
    'modal.export_vpn.success_title' => 'Export Completed',
    'modal.export_vpn.stats_wg'      => 'WireGuard IPs:',
    'modal.export_vpn.stats_sstp' => 'SSTP:',
    'modal.export_vpn.stats_pptp' => 'PPTP:',

    // ── Toast ──────────────────────────────────────────────────
    'toast.default'          => 'Operation completed successfully!',

    // ── Auth ───────────────────────────────────────────────────
    'auth.login_title'       => 'Sign In',
    'auth.password_label'    => 'Password',
    'auth.login_btn'         => 'Sign In',
    'auth.invalid_password'  => 'Invalid password.',
    'auth.logout_btn'        => 'Logout',
    'auth.setup_title'       => 'Set Admin Password',
    'auth.setup_desc'        => 'Choose a password to protect the dashboard.',
    'auth.setup_info'        => 'No password is set yet. Create one now.',
    'auth.setup_new_password' => 'New Password',
    'auth.setup_confirm'     => 'Confirm Password',
    'auth.setup_btn'         => 'Set Password',
    'auth.too_many_attempts' => 'Too many login attempts. Please try again in 5 minutes.',
    'auth.setup_csrf_error'  => 'Invalid security token. Please try again.',
    'auth.setup_minlength'   => 'Password must be at least 8 characters.',
    'auth.setup_mismatch'    => 'Passwords do not match.',
    'auth.user_menu'         => 'User menu',

    // ── API error messages ─────────────────────────────────────
    'api.action_required'    => 'Action not specified.',
    'api.name_required'      => 'Peer name cannot be empty.',
    'api.id_required'        => 'ID is required.',
    'api.id_name_required'   => 'ID and name are required.',
    'api.unknown_action'     => 'Unknown action: %s',
    'api.duplicate_name'     => 'A peer with name "%s" already exists.',

    // ── JavaScript UI strings ──────────────────────────────────
    'js.col_name'             => 'Name & Comment',
    'js.col_ip'               => 'Assigned IP',
    'js.col_handshake'        => 'Handshake',
    'js.col_endpoint'         => 'Endpoint',
    'js.col_traffic'          => 'Traffic',
    'js.col_dnat_port'        => 'DNAT Port',
    'js.col_actions'          => 'Actions',
    'js.export_vpn_download'    => 'Download',
    'js.exporting'              => 'Exporting...',
    'js.export_error'           => 'Export error: %s',
    'js.export_warn_sstp'       => 'Secrets not available (native API mode)',
    'js.disabled_badge'       => 'Disabled',
    'js.toggle_disable_title' => 'Disable peer',
    'js.toggle_enable_title'  => 'Enable peer',
    'js.toggle_disable_confirm' => 'Disabling this peer will immediately disconnect the VPN for this client. Continue?',
    'js.peer_disabled'        => 'Peer disabled successfully!',
    'js.peer_enabled'         => 'Peer enabled successfully!',
    'js.toggle_error'         => 'Error toggling peer: %s',
    'js.interface_disabled'   => 'Interface disabled',
    'js.interface_listen_port' => 'Listen port',

    'js.regenerate_btn'       => 'Regenerate Key & Download Config',
    'js.regenerate_error'     => 'Error: %s',
    'js.dnat_copied'          => 'Winbox Port (DNAT): %d copied!',
    'js.code_copy_failed'     => 'Unable to copy code automatically.',
    'js.hide_offline'         => 'Hide offline',
    'js.show_all'             => 'Show all',
    'js.load_error'           => 'Error loading data: %s',
    'js.api_error'            => 'Unable to load peers from the MikroTik router: %s',
    'js.connection_error'     => 'Backend server connection error.',
    'js.endpoint_na'          => 'Not Connected',
    'js.unnamed'              => 'Unnamed',
    'js.copy_port_title'      => 'Copy Winbox Port (DNAT)',
    'js.download_title'       => 'Download client configuration',
    'js.edit_title'           => 'Edit Name',
    'js.delete_title'         => 'Delete peer',
    'js.creating'             => 'Creating...',
    'js.peer_created'         => 'WireGuard peer added successfully!',
    'js.create_error'         => 'Error during creation: %s',
    'js.file_downloaded'      => 'File %s downloaded!',
    'js.peer_updated'         => 'Peer name updated successfully!',
    'js.update_error'         => 'Error during update: %s',
    'js.peer_deleted'         => 'WireGuard peer removed successfully!',
    'js.delete_error'         => 'Error during deletion: %s',
    'js.regenerate_confirm'   => 'WARNING: Key regeneration will immediately interrupt the VPN on this client until you import the new configuration on the router. Proceed?',
    'js.regenerating'         => 'Regenerating...',
    'js.key_regenerated'      => 'Key regenerated successfully!',
    'js.qrcode_title'         => 'WireGuard client configuration QR code',
    'js.qrcode_alt'           => 'QR code containing the WireGuard client configuration',
    'js.qrcode_error'         => 'Unable to generate the QR code.',
    'js.modal_load_error'     => 'Unable to open the dialog. Please try again.',
    'js.config_updated'       => 'Configuration Updated',
    'js.pagination_info'      => 'Page %d of %d (%d total)',
    'js.empty_title'          => 'No peers found',
    'js.empty_description'    => 'No clients configured on this WireGuard interface.',
    'js.empty_no_results_title' => 'No results',
    'js.empty_no_results_desc'  => 'No peers match your current search or filter.',

    'js.copy_failed'          => 'Unable to copy.',
    'js.name_copied'          => 'Name copied to clipboard!',
    'js.ip_copied'            => 'IP copied to clipboard!',
    'js.copy_name_title'      => 'Copy Name',
    'js.copy_ip_title'        => 'Copy IP',
    'js.code_copied'          => 'Code copied to clipboard!',
    'js.code_auto_copied'     => 'Config copied to clipboard!',
];

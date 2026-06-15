<?php
/**
 * MikroTik WireGuard Peer Manager
 * View — renders the dashboard UI.
 * AJAX API is handled by src/api.php.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/src/MikrotikRestClient.php';
require_once __DIR__ . '/src/WireGuardManager.php';
require_once __DIR__ . '/src/i18n.php';
require_once __DIR__ . '/src/ConfigValidator.php';

$config = require __DIR__ . '/config.php';

try {
    ConfigValidator::validate($config);
} catch (InvalidArgumentException $e) {
    ConfigValidator::renderErrorPage($e->getMessage());
}

$lang = loadLanguage($config['lang'] ?? 'en');

try {
    $maxPeers = WireGuardManager::maxPeers($config['subnet'], $config['server_ip']);
} catch (Exception $e) {
    $maxPeers = '?';
}

// Test connection to CHR
$connectionError = null;
try {
    $client = new MikrotikRestClient(
        $config['host'],
        $config['username'],
        $config['password'],
        $config['ssl_verify'] ?? false
    );
    $client->request('GET', '/interface/wireguard');
} catch (Exception $e) {
    $connectionError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="<?php echo $config['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t($lang, 'site.title'); ?></title>
    <meta name="description" content="<?php echo t($lang, 'site.description'); ?>">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

    <?php if ($connectionError !== null): ?>
        <div class="banner banner-danger" id="errorBanner">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>
            <span><?php printf(t($lang, 'banner.api_error'), '<strong>' . htmlspecialchars($connectionError) . '</strong>'); ?></span>
        </div>
    <?php endif; ?>

    <div class="container">
        <!-- Header -->
        <header>
            <div class="brand">
                <h1><?php echo t($lang, 'header.title'); ?></h1>
                <p><?php echo t($lang, 'header.subtitle'); ?></p>
            </div>

            <div class="status-badge">
                <span class="status-dot <?php echo $connectionError !== null ? 'demo' : 'active'; ?>"></span>
                <span><?php echo t($lang, 'header.router_chr'); ?> <strong><?php echo htmlspecialchars($config['host']); ?></strong></span>
            </div>
        </header>

        <!-- Stats Section -->
        <div class="stats-grid">
            <div class="stat-card" data-max-peers="<?php echo $maxPeers; ?>">
                <span class="stat-title"><?php echo t($lang, 'stats.total_peers'); ?></span>
                <span class="stat-value"><span id="stat-total-peers">-</span> <span id="stat-max-peers">/ <?php echo $maxPeers; ?></span></span>
                <span class="stat-desc"><?php echo t($lang, 'stats.total_peers_desc'); ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-title"><?php echo t($lang, 'stats.subnet'); ?></span>
                <span class="stat-value"><?php echo htmlspecialchars($config['subnet']); ?></span>
                <span class="stat-desc"><?php echo t($lang, 'stats.subnet_prefix'); ?> <?php echo htmlspecialchars($config['server_ip']); ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-title"><?php echo t($lang, 'stats.endpoint'); ?></span>
                <span class="stat-value" style="font-size: 1.15rem; font-weight:600; padding: 0.45rem 0;"><?php echo htmlspecialchars($config['endpoint']); ?></span>
                <span class="stat-desc"><?php echo t($lang, 'stats.endpoint_desc'); ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-title"><?php echo t($lang, 'stats.active_peers'); ?></span>
                <span class="stat-value" id="stat-active-peers">-</span>
                <span class="stat-desc"><?php echo t($lang, 'stats.active_peers_desc'); ?></span>
            </div>
        </div>

        <!-- Toolbar / Control Panel -->
        <div class="control-panel">
            <div class="search-box">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z" /></svg>
                <input type="text" id="searchInput" placeholder="<?php echo t($lang, 'search.placeholder'); ?>">
            </div>
            <button class="btn btn-secondary btn-sm" id="hideOfflineBtn" onclick="toggleHideOffline()" title="<?php echo t($lang, 'search.hide_offline_title'); ?>" style="padding:0.5rem 0.85rem;font-size:0.85rem;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;margin-right:4px;vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                <span id="hideOfflineLabel"><?php echo t($lang, 'search.hide_offline'); ?></span>
            </button>
            <button class="btn btn-primary" onclick="openAddModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                <?php echo t($lang, 'search.add_peer'); ?>
            </button>
        </div>

        <!-- Main Table Card -->
        <div class="table-card" style="position: relative;">
            <div class="loading-overlay" id="tableLoader">
                <div class="spinner"></div>
            </div>

            <table id="peersTable">
                <thead>
                    <tr>
                        <th id="th-name" onclick="sortPeers('name')" style="cursor:pointer;">
                            <?php echo t($lang, 'table.name'); ?> <span id="sort-name-icon" style="font-size:.75rem;margin-left:4px;">↕</span>
                        </th>
                        <th id="th-ip" onclick="sortPeers('ip')" style="cursor:pointer;">
                            <?php echo t($lang, 'table.ip'); ?> <span id="sort-ip-icon" style="font-size:.75rem;margin-left:4px;">↕</span>
                        </th>
                        <th id="th-handshake" onclick="sortPeers('handshake')" style="cursor:pointer;">
                            <?php echo t($lang, 'table.handshake'); ?> <span id="sort-handshake-icon" style="font-size:.75rem;margin-left:4px;">↕</span>
                        </th>
                        <th><?php echo t($lang, 'table.endpoint'); ?></th>
                        <th><?php echo t($lang, 'table.traffic'); ?></th>
                        <th style="text-align: right;"><?php echo t($lang, 'table.actions'); ?></th>
                    </tr>
                </thead>
                <tbody id="peersTableBody">
                    <!-- Loaded dynamically via JS -->
                </tbody>
            </table>

            <div class="empty-state" id="emptyState" style="display: none;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z" /></svg>
                <h3><?php echo t($lang, 'empty.title'); ?></h3>
                <p><?php echo t($lang, 'empty.description'); ?></p>
            </div>
        </div>
    </div>

    <!-- Add Peer Modal -->
    <div class="modal-backdrop" id="addModalBackdrop">
        <div class="modal">
            <div class="modal-header">
                <h3><?php echo t($lang, 'modal.add.title'); ?></h3>
                <button class="close-btn" onclick="closeAddModal()">&times;</button>
            </div>

            <form id="addPeerForm" onsubmit="submitAddPeer(event)">
                <div class="modal-body">
                    <div id="modalFormContent">
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.25rem;">
                            <?php echo t($lang, 'modal.add.description'); ?>
                        </p>
                        <div class="form-group">
                            <label for="peerName"><?php echo t($lang, 'modal.add.label_name'); ?></label>
                            <input type="text" id="peerName" placeholder="<?php echo t($lang, 'modal.add.placeholder_name'); ?>" required autocomplete="off">
                        </div>
                    </div>

                    <!-- Result display after creation -->
                    <div id="modalResultContent" style="display: none;">
                        <div class="success-card">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                            <div class="success-card-content">
                                <h4><?php echo t($lang, 'modal.add.success_title'); ?></h4>
                                <p><?php echo t($lang, 'modal.add.success_desc'); ?></p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><?php echo t($lang, 'modal.add.label_ip'); ?></label>
                            <div class="peer-ip-badge" id="resIp" style="font-size: 1rem; padding: 0.35rem 0.75rem;">-</div>
                        </div>

                        <div class="tab-buttons">
                            <button type="button" class="tab-btn active" onclick="switchAddTab('conf')"><?php echo t($lang, 'modal.add.tab_conf'); ?></button>
                            <button type="button" class="tab-btn" onclick="switchAddTab('script')"><?php echo t($lang, 'modal.add.tab_script'); ?></button>
                        </div>

                        <div id="tab-conf" class="tab-content active">
                            <div class="code-box">
                                <button type="button" class="copy-btn-code" onclick="copyToClipboard('code-conf-text')" title="<?php echo t($lang, 'modal.add.copy_title'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125v-9.75A1.125 1.125 0 0 1 4.875 9.75H8.25m2.25 2.25h9.75c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125v-9.75c0-.621.504-1.125 1.125-1.125Z" /></svg>
                                </button>
                                <pre id="code-conf-text"></pre>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" id="btnDownloadConf" style="width: 100%;">
                                <?php echo t($lang, 'modal.add.download_conf'); ?>
                            </button>
                        </div>

                        <div id="tab-script" class="tab-content">
                            <div class="code-box">
                                <button type="button" class="copy-btn-code" onclick="copyToClipboard('code-script-text')" title="<?php echo t($lang, 'modal.add.copy_title'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125v-9.75A1.125 1.125 0 0 1 4.875 9.75H8.25m2.25 2.25h9.75c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125v-9.75c0-.621.504-1.125 1.125-1.125Z" /></svg>
                                </button>
                                <pre id="code-script-text"></pre>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" id="btnDownloadScript" style="width: 100%;">
                                <?php echo t($lang, 'modal.add.download_script'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" id="modalFooterActions">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()"><?php echo t($lang, 'modal.add.cancel'); ?></button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitAdd"><?php echo t($lang, 'modal.add.submit'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Name Modal -->
    <div class="modal-backdrop" id="editModalBackdrop">
        <div class="modal">
            <div class="modal-header">
                <h3><?php echo t($lang, 'modal.edit.title'); ?></h3>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>

            <form id="editPeerForm" onsubmit="submitEditPeer(event)">
                <input type="hidden" id="editPeerId">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="editPeerName"><?php echo t($lang, 'modal.edit.label_name'); ?></label>
                        <input type="text" id="editPeerName" required autocomplete="off">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()"><?php echo t($lang, 'modal.edit.cancel'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo t($lang, 'modal.edit.submit'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirm Modal -->
    <div class="modal-backdrop" id="deleteModalBackdrop">
        <div class="modal" style="max-width: 450px;">
            <div class="modal-header">
                <h3 style="color: var(--danger);"><?php echo t($lang, 'modal.delete.title'); ?></h3>
                <button class="close-btn" onclick="closeDeleteModal()">&times;</button>
            </div>

            <div class="modal-body">
                <p><?php printf(t($lang, 'modal.delete.confirm'), '<strong id="deletePeerNameText" style="color: var(--danger);"></strong>'); ?></p>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.5rem;">
                    <?php echo t($lang, 'modal.delete.description'); ?>
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()"><?php echo t($lang, 'modal.delete.cancel'); ?></button>
                <button type="button" class="btn btn-danger" id="btnConfirmDelete"><?php echo t($lang, 'modal.delete.submit'); ?></button>
            </div>
        </div>
    </div>

    <!-- Export Config Modal -->
    <div class="modal-backdrop" id="exportModalBackdrop">
        <div class="modal">
            <div class="modal-header">
                <h3><?php echo t($lang, 'modal.export.title'); ?></h3>
                <button class="close-btn" onclick="closeExportModal()">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-row" style="display:flex; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group" style="flex:1;">
                        <label><?php echo t($lang, 'modal.export.label_ip'); ?></label>
                        <div class="peer-ip-badge" id="exportIp" style="font-size: 1rem; padding: 0.35rem 0.75rem;">-</div>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label><?php echo t($lang, 'modal.export.label_port'); ?></label>
                        <div class="peer-ip-badge" id="exportPort" style="font-size: 1rem; padding: 0.35rem 0.75rem;">-</div>
                    </div>
                </div>

                <div style="text-align:center; margin-bottom:1.25rem;">
                    <button class="btn btn-warning btn-sm" id="btnRegenerateKey" onclick="regenerateKey()" style="width:100%;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                        <?php echo t($lang, 'modal.export.regenerate_btn'); ?>
                    </button>
                    <p style="color:var(--text-muted); font-size:0.8rem; margin-top:0.5rem;">
                        <?php echo t($lang, 'modal.export.regenerate_desc'); ?>
                    </p>
                </div>

                <div id="exportConfigSection" style="display:none;">
                    <div class="tab-buttons">
                        <button type="button" class="tab-btn" id="tabBtnExportConf" onclick="switchExportTab('conf')"><?php echo t($lang, 'modal.export.tab_conf'); ?></button>
                        <button type="button" class="tab-btn active" id="tabBtnExportScript" onclick="switchExportTab('script')"><?php echo t($lang, 'modal.export.tab_script'); ?></button>
                    </div>

                    <div id="tab-export-conf" class="tab-content">
                        <div class="code-box">
                            <button type="button" class="copy-btn-code" onclick="copyToClipboard('code-export-conf-text')" title="<?php echo t($lang, 'modal.add.copy_title'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125v-9.75A1.125 1.125 0 0 1 4.875 9.75H8.25m2.25 2.25h9.75c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125v-9.75c0-.621.504-1.125 1.125-1.125Z" /></svg>
                            </button>
                            <pre id="code-export-conf-text"></pre>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" id="btnDownloadExportConf" style="width: 100%;">
                            <?php echo t($lang, 'modal.export.download_conf'); ?>
                        </button>
                    </div>

                    <div id="tab-export-script" class="tab-content active">
                        <div class="code-box">
                            <button type="button" class="copy-btn-code" onclick="copyToClipboard('code-export-script-text')" title="<?php echo t($lang, 'modal.add.copy_title'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125v-9.75A1.125 1.125 0 0 1 4.875 9.75H8.25m2.25 2.25h9.75c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125v-9.75c0-.621.504-1.125 1.125-1.125Z" /></svg>
                            </button>
                            <pre id="code-export-script-text"></pre>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" id="btnDownloadExportScript" style="width: 100%;">
                            <?php echo t($lang, 'modal.export.download_script'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeExportModal()"><?php echo t($lang, 'modal.export.close'); ?></button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" style="color: var(--success);" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>
        <span id="toastText"><?php echo t($lang, 'toast.default'); ?></span>
    </div>

    <script>
        const AppConfig = {
            endpoint: <?php echo json_encode($config['endpoint']); ?>,
            clientAllowedIps: <?php echo json_encode($config['client_allowed_ips']); ?>,
            serverPublicKey: '',
            dnatBase: <?php echo json_encode($config['dnat_base'] ?? 30000); ?>,
            dnatMultiplier: <?php echo json_encode($config['dnat_multiplier'] ?? 1000); ?>,
            translations: <?php echo json_encode(jsTranslations($lang), JSON_UNESCAPED_UNICODE); ?>,
        };
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>

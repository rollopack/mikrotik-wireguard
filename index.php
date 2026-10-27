<?php
// Copyright (C) 2026 Rolland Gabriel (https://github.com/rollopack)
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <https://www.gnu.org/licenses/>.

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/src/WireGuardManager.php';
require_once __DIR__ . '/src/i18n.php';
require_once __DIR__ . '/src/ConfigValidator.php';
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/ConfigManager.php';
require_once __DIR__ . '/src/AppInfo.php';

$config = require __DIR__ . '/config.php';

if (isset($_GET['logout'])) {
    logout();
    header('Location: login.php');
    exit;
}

try {
    ConfigValidator::validate($config);
} catch (InvalidArgumentException $e) {
    ConfigValidator::renderErrorPage($e->getMessage());
}

requireAuth();

ConfigManager::persistServerKey($config['_server_key']);
$availableServers = ConfigManager::getAvailableServers();
$currentServerKey = $config['_server_key'];
$currentServerName = $availableServers[$currentServerKey]['name'] ?? $currentServerKey;

$cspNonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; font-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'nonce-{$cspNonce}'; img-src 'self' data:;");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$lang = loadLanguage($config['lang'] ?? 'en');

$assetVersionValue = AppInfo::assetVersion();
$assetVersion = rawurlencode($assetVersionValue);

try {
    $maxPeers = WireGuardManager::maxPeers($config['subnet'], $config['server_ip']);
} catch (Exception $e) {
    $maxPeers = '?';
}

// No RouterOS request happens while rendering this page: the peer list and the
// interface status arrive from src/api.php?action=get_peers, so the document
// (and the CSS/JS/fonts) can be delivered and painted immediately.
// Modals are fetched on demand from src/modal.php.
?>
<!DOCTYPE html>
<html lang="<?php echo $config['lang'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t($lang, 'site.title'); ?></title>
    <meta name="description" content="<?php echo t($lang, 'site.description'); ?>">
    <!-- Self-hosted Outfit webfont: no third-party request on the critical path -->
    <link rel="preload" href="assets/fonts/outfit-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="assets/css/fonts.css?v=<?php echo $assetVersion; ?>">
    <link rel="stylesheet" href="assets/css/app.css?v=<?php echo $assetVersion; ?>">
</head>
<body>

    <div class="banner banner-danger" id="errorBanner" role="alert" hidden>
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>
        <span id="errorBannerText"></span>
    </div>

    <main class="container">
        <!-- Header -->
        <header>
            <div class="brand">
                <h1><?php echo t($lang, 'header.title'); ?></h1>
                <p><?php echo t($lang, 'header.subtitle'); ?></p>
            </div>

            <div class="status-badge">
                <span class="status-dot" id="statusDot"></span>
                <span><?php echo t($lang, 'header.router_chr'); ?> <strong><?php echo htmlspecialchars($config['host']); ?></strong> <span id="wgInterfaceLabel" style="display:none;"></span></span>
                <?php
                    $apiMode = $config['api_mode'] ?? 'rest';
                    $apiModeLabel = ($apiMode === 'native')
                        ? sprintf(t($lang, 'header.api_native'), $config['native_api']['port'] ?? 8728)
                        : t($lang, 'header.api_rest');
                    echo '<span class="api-mode-badge">' . t($lang, 'header.api_mode') . ' ' . $apiModeLabel . '</span>';
                ?>
                <select id="serverSelector" class="server-selector" aria-label="<?php echo t($lang, 'header.server_select'); ?>">
                    <?php foreach ($availableServers as $key => $srv): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $key === $currentServerKey ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($srv['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                    echo '<button type="button" class="auth-badge" data-action="open-about" title="' . t($lang, 'header.about') . '" aria-label="' . t($lang, 'header.about') . '">?</button>';
                    echo '<nav aria-label="' . t($lang, 'auth.user_menu') . '"><a href="?logout" class="auth-badge" title="' . t($lang, 'auth.logout_btn') . '"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:14px;height:14px;vertical-align:middle;margin-right:3px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" /></svg>' . t($lang, 'auth.logout_btn') . '</a></nav>';
                ?>
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
                <span class="stat-value stat-value-compact"><?php echo htmlspecialchars($config['endpoint']); ?></span>
                <span class="stat-desc"><?php echo t($lang, 'stats.endpoint_desc'); ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-title"><?php echo t($lang, 'stats.active_peers'); ?></span>
                <span class="stat-value" id="stat-active-peers">-</span>
                <span class="stat-desc"><?php echo sprintf(t($lang, 'stats.active_peers_desc'), $config['handshake_timeout'] ?? 5); ?></span>
            </div>
        </div>

        <!-- Toolbar / Control Panel -->
        <div class="control-panel">
            <div class="search-box">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z" /></svg>
                <input type="text" id="searchInput" placeholder="<?php echo t($lang, 'search.placeholder'); ?>" aria-label="<?php echo t($lang, 'search.placeholder'); ?>">
            </div>
            <button class="btn btn-secondary btn-sm" id="hideOfflineBtn" title="<?php echo t($lang, 'search.hide_offline_title'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;margin-right:4px;vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                <span id="hideOfflineLabel"><?php echo t($lang, 'js.hide_offline'); ?></span>
            </button>
            <button class="btn btn-primary" data-action="open-add">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                <?php echo t($lang, 'search.add_peer'); ?>
            </button>
            <button class="btn btn-secondary btn-sm" data-action="open-export-vpn" title="<?php echo htmlspecialchars(t($lang, 'modal.export_vpn.title')); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:18px;height:18px;margin-right:4px;vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                <span><?php echo t($lang, 'modal.export_vpn.title'); ?></span>
            </button>
        </div>

        <!-- Main Table Card -->
        <div class="table-card" style="position: relative;">
            <div class="loading-overlay" id="tableLoader">
                <div class="spinner"></div>
            </div>

            <table id="peersTable" aria-label="<?php echo t($lang, 'table.aria_label'); ?>">
                <thead>
                    <tr>
                        <th id="th-name" class="th-sortable" data-action="sort" data-sort="name" tabindex="0" role="button">
                            <?php echo t($lang, 'table.name'); ?> <span id="sort-name-icon" class="sort-icon">↕</span>
                        </th>
                        <th id="th-ip" class="th-sortable" data-action="sort" data-sort="ip" tabindex="0" role="button">
                            <?php echo t($lang, 'table.ip'); ?> <span id="sort-ip-icon" class="sort-icon">↕</span>
                        </th>
                        <?php if (!empty($config['show_dnat_column'])): ?>
                        <th id="th-dnat-port" class="th-sortable" data-action="sort" data-sort="dnat" tabindex="0" role="button">
                            <?php echo t($lang, 'table.dnat_port'); ?> <span id="sort-dnat-icon" class="sort-icon">↕</span>
                        </th>
                        <?php endif; ?>
                        <th id="th-handshake" class="th-sortable" data-action="sort" data-sort="handshake" tabindex="0" role="button">
                            <?php echo t($lang, 'table.handshake'); ?> <span id="sort-handshake-icon" class="sort-icon">↕</span>
                        </th>
                        <th><?php echo t($lang, 'table.endpoint'); ?></th>
                        <?php if ($config['show_traffic_column'] ?? true): ?>
                        <th><?php echo t($lang, 'table.traffic'); ?></th>
                        <?php endif; ?>
                        <th class="text-right"><?php echo t($lang, 'table.actions'); ?></th>
                    </tr>
                </thead>
                <tbody id="peersTableBody">
                    <!-- Loaded dynamically via JS -->
                </tbody>
            </table>

            <div class="empty-state" id="emptyState" style="display: none;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.5 10.5a5.25 5.25 0 017 0M7.5 7.5a8.25 8.25 0 0113 0" /></svg>
                <h3 id="emptyStateTitle"></h3>
                <p id="emptyStateDesc"></p>
            </div>

            <div class="pagination" id="pagination" style="display:none;">
                <button class="pagination-btn" id="prevPageBtn" data-action="prev-page">‹</button>
                <span class="pagination-info" id="paginationInfo"></span>
                <button class="pagination-btn" id="nextPageBtn" data-action="next-page">›</button>
            </div>
        </div>
    </main>

    <!-- Modals are fetched on demand by app.js (src/modal.php) -->
    <div id="modalHost"></div>

    <!-- Toast Notification -->
    <div class="toast" id="toast" role="status" aria-live="polite">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" style="color: var(--success);" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>
        <span id="toastText"><?php echo t($lang, 'toast.default'); ?></span>
    </div>

    <script nonce="<?php echo $cspNonce; ?>">
        const AppConfig = {
            endpointPort: <?php echo json_encode(WireGuardManager::extractEndpointPort($config['endpoint'] ?? '')); ?>,
            dnatBase: <?php echo json_encode($config['dnat_base'] ?? 30000); ?>,
            dnatMultiplier: <?php echo json_encode($config['dnat_multiplier'] ?? 1000); ?>,
            refreshInterval: <?php echo json_encode(($config['refresh_interval'] ?? 30) * 1000); ?>,
            handshakeTimeout: <?php echo json_encode(($config['handshake_timeout'] ?? 5) * 60); ?>,
            pageSize: <?php echo json_encode($config['page_size'] ?? 50); ?>,
            serverKey: <?php echo json_encode($currentServerKey); ?>,
            translations: <?php echo json_encode(jsTranslations($lang), JSON_UNESCAPED_UNICODE); ?>,
            csrfToken: <?php echo json_encode(getCsrfToken()); ?>,
            exportMode: <?php echo json_encode($config['export_mode'] ?? 'rsc'); ?>,
            showDnatColumn: <?php echo json_encode($config['show_dnat_column'] ?? false); ?>,
            showTrafficColumn: <?php echo json_encode($config['show_traffic_column'] ?? true); ?>,
            assetVersion: <?php echo json_encode($assetVersionValue); ?>,
        };
    </script>
    <script src="assets/js/app.js?v=<?php echo $assetVersion; ?>" defer></script>
</body>
</html>

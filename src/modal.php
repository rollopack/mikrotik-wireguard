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

/**
 * Serve a single modal fragment on demand.
 *
 * The dashboard no longer ships the seven modal bodies in index.php: the
 * frontend fetches them from here the first time they are opened, which keeps
 * ~22 KB of markup (and the rendered README) out of the initial page weight.
 *
 * Request: GET ?type=<add|edit|delete|confirm|export|export-vpn-ips|about>
 *                &server=<config key>
 * Response: text/html fragment containing one `.modal-backdrop` element.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/ConfigManager.php';
require_once __DIR__ . '/AppInfo.php';
require_once __DIR__ . '/MarkdownRenderer.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/**
 * Available fragments, keyed by the `type` request value.
 */
const MODAL_FRAGMENTS = [
    'add' => 'add',
    'edit' => 'edit',
    'delete' => 'delete',
    'confirm' => 'confirm',
    'export' => 'export',
    'export-vpn-ips' => 'export-vpn-ips',
    'about' => 'about',
];

if (!isAuthEnabled()) {
    http_response_code(409);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Setup required';
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unauthorized';
    exit;
}

// Reject an unknown ?server= instead of silently falling back to the default
// config (same rule as src/api.php and src/public-api.php).
$serverError = ConfigManager::validateRequestedServer($_GET['server'] ?? null);
if ($serverError !== null) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo $serverError;
    exit;
}

$type = $_GET['type'] ?? '';
if (!is_string($type) || !isset(MODAL_FRAGMENTS[$type])) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unknown modal type.';
    exit;
}

$config = require __DIR__ . '/../config.php';
$lang = loadLanguage($config['lang'] ?? 'en');

if ($type === 'about') {
    $appVersion = AppInfo::version();
    $readmePath = __DIR__ . '/../README.md';
    $readmeHtml = is_file($readmePath)
        ? MarkdownRenderer::render((string)file_get_contents($readmePath), [
            'link_base' => AppInfo::repositoryUrl() . '/blob/main/',
            // Skip the installation-oriented sections and open on Configuration.
            'from_heading' => 'Configuration',
        ])
        : '';
}

header('Content-Type: text/html; charset=utf-8');

require __DIR__ . '/views/modals/' . MODAL_FRAGMENTS[$type] . '.php';

<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/ClientFactory.php';
require_once __DIR__ . '/WireGuardManager.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/ConfigValidator.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ConfigManager.php';
require_once __DIR__ . '/VpnIpExporter.php';

$config = require __DIR__ . '/../config.php';

try {
    ConfigValidator::validate($config);
} catch (InvalidArgumentException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Configuration error: ' . $e->getMessage()]);
    exit;
}

requireAuth();

$lang = loadLanguage($config['lang'] ?? 'en');


$client = ClientFactory::create($config);
$manager = new WireGuardManager($client, $config);

header('Content-Type: application/json');
header('Cache-Control: no-store');

// Reject an unknown ?server= instead of silently falling back to the default
// config (checked via ConfigManager, shared with src/public-api.php).
$serverError = ConfigManager::validateRequestedServer($_GET['server'] ?? null);
if ($serverError !== null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $serverError]);
    exit;
}

if (!isset($_GET['action'])) {
    echo json_encode(['success' => false, 'error' => t($lang, 'api.action_required')]);
    exit;
}

try {
    if ($_GET['action'] === 'get_peers') {
        // One backend round trip: peers and interface status together.
        $snapshot = $client->getPeersAndInterface();
        echo json_encode([
            'success' => true,
            'peers' => $snapshot['peers'],
            'interface' => $snapshot['interface'],
        ]);
        exit;
    }

    if ($_GET['action'] === 'get_interface_status') {
        echo json_encode([
            'success' => true,
            'interface' => $client->getInterfaceStatus(),
        ]);
        exit;
    }

    if ($_GET['action'] === 'check_session') {
        echo json_encode(['success' => true]);
        exit;
    }

    requireCsrf();

    if ($_GET['action'] === 'add_peer') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            throw new Exception(t($lang, 'api.name_required'));
        }
        try {
            $result = $manager->addPeer($name);
            echo json_encode(['success' => true, 'peer' => $result]);
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'already exists')) {
                echo json_encode(['success' => false, 'error' => sprintf(t($lang, 'api.duplicate_name'), $name)]);
            } else {
                throw $e;
            }
            exit;
        }
        exit;
    }

    if ($_GET['action'] === 'regenerate_key') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = trim($input['id'] ?? '');
        if (empty($id)) {
            throw new Exception(t($lang, 'api.id_required'));
        }
        $keys = $manager->regenerateKey($id);

        $peers = $manager->getPeers();
        $peerData = null;
        foreach ($peers as $p) {
            if ($p['.id'] === $id) {
                $peerData = $p;
                break;
            }
        }

        $confContent = null;
        $scriptContent = null;
        if ($peerData) {
            $peerIp = explode('/', $peerData['allowed-address'] ?? '')[0];
            $serverPubKey = $manager->getServerPublicKey();
            $confContent = WireGuardManager::generateConfig(
                $peerIp, $keys['private_key'], $serverPubKey,
                $config['endpoint'], $config['client_allowed_ips'],
                $config['client_dns'] ?? ''
            );
            $scriptContent = WireGuardManager::generateRscScript(
                $peerIp, $keys['private_key'], $serverPubKey,
                $config['endpoint'], $config['client_allowed_ips'],
                $config['interface'],
                $config['comment'] ?? $config['interface'],
                $config['server_ip'],
                $config['subnet'],
                $config['client_dns'] ?? ''
            );
        }

        echo json_encode([
            'success' => true,
            'public_key' => $keys['public_key'],
            'private_key' => $keys['private_key'],
            'config' => $confContent,
            'script' => $scriptContent,
        ]);
        exit;
    }

    if ($_GET['action'] === 'update_peer') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = trim($input['id'] ?? '');
        $name = trim($input['name'] ?? '');
        if (empty($id) || empty($name)) {
            throw new Exception(t($lang, 'api.id_name_required'));
        }
        $manager->updatePeer($id, $name);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_GET['action'] === 'toggle_peer') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = trim($input['id'] ?? '');
        if (empty($id)) {
            throw new Exception(t($lang, 'api.id_required'));
        }
        $disabled = !empty($input['disabled']);
        $manager->togglePeer($id, $disabled);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_GET['action'] === 'delete_peer') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = trim($input['id'] ?? '');
        if (empty($id)) {
            throw new Exception(t($lang, 'api.id_required'));
        }
        $manager->deletePeer($id);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_GET['action'] === 'export_vpn_ips') {
        $input = json_decode(file_get_contents('php://input'), true);
        $includeSstp = !empty($input['include_sstp']);
        $includePptp = !empty($input['include_pptp']);

        $exporter = new VpnIpExporter($client);
        $wgIps = $exporter->wireguardIps();

        $secretIpsSstp = [];
        $secretIpsPptp = [];
        $secretError = null;

        if ($includeSstp) {
            $secretIpsSstp = $exporter->serviceIps('sstp', true, $secretError);
        }
        if ($includePptp) {
            $secretIpsPptp = $exporter->serviceIps('pptp', true, $secretError);
        }

        $lines = [];
        $lines[] = '# WireGuard';
        $lines = array_merge($lines, $wgIps);
        if ($includeSstp && count($secretIpsSstp) > 0) {
            $lines[] = '';
            $lines[] = '# SSTP';
            $lines = array_merge($lines, $secretIpsSstp);
        }
        if ($includePptp && count($secretIpsPptp) > 0) {
            $lines[] = '';
            $lines[] = '# PPTP';
            $lines = array_merge($lines, $secretIpsPptp);
        }
        $lines[] = '';

        echo json_encode([
            'success' => true,
            'content' => implode("\n", $lines),
            'filename' => 'vpn-ips.txt',
            'stats' => [
                'wireguard' => count($wgIps),
                'sstp' => count($secretIpsSstp),
                'pptp' => count($secretIpsPptp),
            ],
            'secret_error' => $secretError,
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => sprintf(t($lang, 'api.unknown_action'), $_GET['action'])]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

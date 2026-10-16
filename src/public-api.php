<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/ClientFactory.php';
require_once __DIR__ . '/WireGuardManager.php';
require_once __DIR__ . '/ConfigValidator.php';
require_once __DIR__ . '/ConfigManager.php';
require_once __DIR__ . '/auth.php';

$config = require __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

try {
    ConfigValidator::validate($config);
} catch (InvalidArgumentException $e) {
    echo json_encode(['success' => false, 'error' => 'Configuration error: ' . $e->getMessage()]);
    exit;
}

requireApiToken();

// Reject an unknown ?server= instead of silently falling back to the default
// config (checked via ConfigManager, shared with src/api.php).
$serverError = ConfigManager::validateRequestedServer($_GET['server'] ?? null);
if ($serverError !== null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $serverError]);
    exit;
}

$action = $_GET['action'] ?? '';
if (!in_array($action, ['create_peer', 'check_peer', 'regenerate_peer', 'delete_peer', 'toggle_peer'], true)) {
    echo json_encode(['success' => false, 'error' => 'Unknown or missing action. Supported: create_peer, check_peer, regenerate_peer, delete_peer, toggle_peer']);
    exit;
}

$client = ClientFactory::create($config);
$manager = new WireGuardManager($client, $config);

try {
    if ($action === 'check_peer') {
        $name = trim($_GET['name'] ?? '');
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => 'Peer name cannot be empty.']);
            exit;
        }
        $peer = $manager->findPeerByName($name);
        echo json_encode([
            'success' => true,
            'exists' => $peer !== null,
            'peer' => $peer !== null ? ['name' => $peer['name'], 'ip' => explode('/', $peer['allowed-address'] ?? '')[0]] : null,
        ]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
        exit;
    }

    $name = trim($input['name'] ?? '');
    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Peer name cannot be empty.']);
        exit;
    }

    if ($action === 'create_peer') {
        try {
            $result = $manager->addPeer($name);
            echo json_encode(['success' => true, 'peer' => $result]);
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'already exists')) {
                echo json_encode(['success' => false, 'error' => 'A peer with name "' . $name . '" already exists.']);
            } else {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }
        exit;
    }

    if ($action === 'regenerate_peer') {
        $result = $manager->regeneratePeer($name);
        echo json_encode(['success' => true, 'peer' => $result]);
        exit;
    }

    if ($action === 'delete_peer') {
        $result = $manager->deletePeerByName($name);
        echo json_encode(['success' => true, 'deleted' => $result]);
        exit;
    }

    if ($action === 'toggle_peer') {
        $disabled = WireGuardManager::parseBoolStrict($input['disabled'] ?? null);
        if ($disabled === null) {
            echo json_encode(['success' => false, 'error' => 'Invalid disabled value.']);
            exit;
        }
        $result = $manager->togglePeerByName($name, $disabled);
        echo json_encode(['success' => true, 'peer' => $result]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

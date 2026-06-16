<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/ClientFactory.php';
require_once __DIR__ . '/WireGuardManager.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/ConfigValidator.php';

$config = require __DIR__ . '/../config.php';

try {
    ConfigValidator::validate($config);
} catch (InvalidArgumentException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Configuration error: ' . $e->getMessage()]);
    exit;
}

$lang = loadLanguage($config['lang'] ?? 'en');

$client = ClientFactory::create($config);
$manager = new WireGuardManager($client, $config);
$manager->getServerPublicKey();

header('Content-Type: application/json');

if (!isset($_GET['action'])) {
    echo json_encode(['success' => false, 'error' => t($lang, 'api.action_required')]);
    exit;
}

try {
    if ($_GET['action'] === 'get_peers') {
        echo json_encode([
            'success' => true,
            'peers' => $manager->getPeers(),
            'server_public_key' => $manager->getServerPublicKey()
        ]);
        exit;
    }

    if ($_GET['action'] === 'add_peer') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            throw new Exception(t($lang, 'api.name_required'));
        }
        $result = $manager->addPeer($name);
        echo json_encode(['success' => true, 'peer' => $result]);
        exit;
    }

    if ($_GET['action'] === 'regenerate_key') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = trim($input['id'] ?? '');
        if (empty($id)) {
            throw new Exception(t($lang, 'api.id_required'));
        }
        $keys = $manager->regenerateKey($id);
        echo json_encode([
            'success' => true,
            'public_key' => $keys['public_key'],
            'private_key' => $keys['private_key']
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

    echo json_encode(['success' => false, 'error' => sprintf(t($lang, 'api.unknown_action'), $_GET['action'])]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

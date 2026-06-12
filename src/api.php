<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/MikrotikRestClient.php';
require_once __DIR__ . '/WireGuardManager.php';
require_once __DIR__ . '/DemoWireGuardManager.php';

$config = require __DIR__ . '/../config.php';

$isDemoMode = ($config['password'] === 'password' || isset($_GET['demo']));
$manager = null;

if (!$isDemoMode) {
    try {
        $client = new MikrotikRestClient(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['ssl_verify'] ?? false
        );
        $manager = new WireGuardManager($client, $config);
        $manager->getServerPublicKey();
    } catch (Exception $e) {
        $isDemoMode = true;
    }
}

if ($isDemoMode) {
    $manager = new DemoWireGuardManager($config);
}

header('Content-Type: application/json');

if (!isset($_GET['action'])) {
    echo json_encode(['success' => false, 'error' => 'Azione non specificata.']);
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
            throw new Exception("Il nome del peer non può essere vuoto.");
        }
        $result = $manager->addPeer($name);
        echo json_encode(['success' => true, 'peer' => $result]);
        exit;
    }

    if ($_GET['action'] === 'regenerate_key') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = trim($input['id'] ?? '');
        if (empty($id)) {
            throw new Exception("ID obbligatorio.");
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
            throw new Exception("ID e nome sono obbligatori.");
        }
        $manager->updatePeer($id, $name);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_GET['action'] === 'delete_peer') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = trim($input['id'] ?? '');
        if (empty($id)) {
            throw new Exception("ID obbligatorio.");
        }
        $manager->deletePeer($id);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Azione sconosciuta: ' . $_GET['action']]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

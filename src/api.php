<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/ClientFactory.php';
require_once __DIR__ . '/WireGuardManager.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/ConfigValidator.php';
require_once __DIR__ . '/auth.php';

$config = require __DIR__ . '/../config.php';

try {
    ConfigValidator::validate($config);
} catch (InvalidArgumentException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Configuration error: ' . $e->getMessage()]);
    exit;
}

requireAuth($config);

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

    if ($_GET['action'] === 'export_vpn_ips') {
        $input = json_decode(file_get_contents('php://input'), true);
        $includeSstp = !empty($input['include_sstp']);
        $includePptp = !empty($input['include_pptp']);

        $peers = $client->getPeers();
        $wgIps = [];
        foreach ($peers as $peer) {
            if (!empty($peer['allowed-address'])) {
                $parts = explode(',', $peer['allowed-address']);
                foreach ($parts as $cidr) {
                    $cidr = trim($cidr);
                    $ip = explode('/', $cidr)[0];
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $wgIps[] = $ip;
                    }
                }
            }
        }
        $wgIps = array_unique($wgIps);
        usort($wgIps, function ($a, $b) {
            return strcmp(inet_pton($a), inet_pton($b));
        });

        $secretIpsSstp = [];
        $secretIpsPptp = [];
        $secretError = null;

        $fetchSecretIps = function (string $service) use ($client, &$secretError): array {
            $ips = [];
            try {
                $secrets = $client->request('GET', '/ppp/secret');
                foreach ($secrets as $secret) {
                    $disabled = $secret['disabled'] ?? 'no';
                    if (($secret['service'] ?? '') === $service && $disabled !== 'yes' && $disabled !== 'true') {
                        $addr = $secret['remote-address'] ?? $secret['address'] ?? '';
                        if (!empty($addr) && filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                            $ips[] = $addr;
                        }
                    }
                }
            } catch (Exception $e) {
                if ($secretError === null) {
                    $secretError = $e->getMessage();
                }
            }

            if (count($ips) === 0 && $secretError === null) {
                try {
                    $active = $client->request('GET', '/ppp/active');
                    foreach ($active as $session) {
                        if (($session['service'] ?? '') === $service) {
                            $addr = $session['address'] ?? $session['remote-address'] ?? '';
                            if (!empty($addr) && filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                                $ips[] = $addr;
                            }
                        }
                    }
                } catch (Exception $e) {
                    if ($secretError === null) {
                        $secretError = $e->getMessage();
                    }
                }
            }

            $ips = array_unique($ips);
            if (count($ips) > 0) {
                usort($ips, function ($a, $b) {
                    return strcmp(inet_pton($a), inet_pton($b));
                });
            }

            return $ips;
        };

        if ($includeSstp) {
            $secretIpsSstp = $fetchSecretIps('sstp');
        }
        if ($includePptp) {
            $secretIpsPptp = $fetchSecretIps('pptp');
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

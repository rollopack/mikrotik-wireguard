#!/usr/bin/env php
<?php

require_once __DIR__ . '/ClientFactory.php';
require_once __DIR__ . '/WireGuardManager.php';

$config = require __DIR__ . '/../config.php';

$outputFile = $argv[1] ?? __DIR__ . '/../vpn-ips.txt';

try {
    $client = ClientFactory::create($config);

    // WireGuard peers
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

    // SSTP secrets
    $secrets = $client->getPppSecrets();
    $sstpIps = [];
    foreach ($secrets as $secret) {
        $disabled = $secret['disabled'] ?? 'no';
        $service = $secret['service'] ?? '';
        if ($service === 'sstp' && $disabled !== 'yes' && $disabled !== 'true') {
            if (!empty($secret['remote-address'])) {
                $ip = $secret['remote-address'];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $sstpIps[] = $ip;
                }
            }
        }
    }
    $sstpIps = array_unique($sstpIps);
    usort($sstpIps, function ($a, $b) {
        return strcmp(inet_pton($a), inet_pton($b));
    });

    // Build output
    $lines = [];
    if (count($wgIps) > 0) {
        $lines[] = '# WireGuard';
        $lines = array_merge($lines, $wgIps);
    }
    if (count($sstpIps) > 0) {
        if (count($lines) > 0) {
            $lines[] = '';
        }
        $lines[] = '# SSTP';
        $lines = array_merge($lines, $sstpIps);
    }
    $lines[] = '';

    file_put_contents($outputFile, implode("\n", $lines));

    $total = count($wgIps) + count($sstpIps);
    echo "Esportazione completata: IP salvati -> $outputFile\n";
    echo "  WireGuard: " . count($wgIps) . "\n";
    echo "  SSTP: " . count($sstpIps) . "\n";

} catch (Exception $e) {
    fwrite(STDERR, "Errore: " . $e->getMessage() . "\n");
    exit(1);
}

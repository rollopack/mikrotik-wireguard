#!/usr/bin/env php
<?php

require_once __DIR__ . '/ClientFactory.php';
require_once __DIR__ . '/WireGuardManager.php';
require_once __DIR__ . '/VpnIpExporter.php';

$config = require __DIR__ . '/../config.php';

$outputFile = $argv[1] ?? __DIR__ . '/../vpn-ips.txt';

try {
    $client = ClientFactory::create($config);
    $exporter = new VpnIpExporter($client);

    // WireGuard peers
    $wgIps = $exporter->wireguardIps();

    // SSTP secrets (no active fallback for the CLI export)
    $secretError = null;
    $sstpIps = $exporter->serviceIps('sstp', false, $secretError);
    if ($secretError !== null) {
        throw new Exception($secretError);
    }

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

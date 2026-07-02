<?php

require_once __DIR__ . '/ClientInterface.php';

class MikrotikApiClient implements ClientInterface
{
    private array $config;
    private string $mode;
    
    public function __construct(array $config, string $mode = 'native')
    {
        $this->config = $config;
        $this->mode = $mode;
    }
    
    public function request(string $method, string $path, ?array $data = null): array
    {
        // For native mode, delegate to specific methods
        // This is kept for backward compatibility with any code still using request()
        throw new Exception("REST request() not supported in native mode. Use specific interface methods.");
    }

    public function getPeers(): array
    {
        return $this->getPeersViaNativeApi([]);
    }
    
    public function getAllPeers(): array
    {
        return $this->queryNativeApi([], 'get_all_peers');
    }
    
    public function getServerPublicKey(): string
    {
        $result = $this->queryNativeApi([], 'get_interface');
        $ifaceName = $this->getInterface();
        if (isset($result[$ifaceName]) && isset($result[$ifaceName]['public-key'])) {
            return $result[$ifaceName]['public-key'];
        }
        
        // Fallback: try to get first interface with public-key
        foreach ($result as $ifaceData) {
            if (isset($ifaceData['public-key'])) {
                return $ifaceData['public-key'];
            }
        }
        
        throw new Exception("WireGuard interface '" . $ifaceName . "' not found or has no public key on the MikroTik CHR.");
    }
    
    public function getInterface(): string
    {
        return $this->config['interface'] ?? 'WireGuard-ResNovae';
    }
    
    public function addPeer(array $payload): array
    {
        $result = $this->queryNativeApi([], 'add_peer', ['payload' => $payload]);
        return $result;
    }
    
    public function updatePeer(string $id, array $payload): void
    {
        $this->queryNativeApi([], 'update_peer', ['peer_id' => $id, 'update_data' => $payload]);
    }
    
    public function deletePeer(string $id): void
    {
        $this->queryNativeApi([], 'delete_peer', ['peer_id' => $id]);
    }
    
    public function getPppSecrets(): array
    {
        return $this->queryNativeApi([], 'get_ppp_secrets');
    }
    
    public function getPppActive(): array
    {
        return $this->queryNativeApi([], 'get_ppp_active');
    }
    
    private function getPeersViaNativeApi(array $peerNames = []): array
    {
        $nativeData = $this->queryNativeApi($peerNames);
        
        if (empty($nativeData)) {
            return [];
        }
        
        $filteredPeers = [];
        foreach ($nativeData as $name => $data) {
            // Skip the interface entry (public key)
            if ($name === ($this->config['interface'] ?? 'WireGuard-ResNovae')) {
                continue;
            }
            
            $peer = [
                '.id' => $data['.id'] ?? '',
                'name' => $name,
                'allowed-address' => $data['allowed-address'] ?? '',
                'last-handshake' => $data['last-handshake'] ?? '',
                'current-endpoint-address' => $data['current-endpoint-address'] ?? '',
                'public-key' => $data['public-key'] ?? '',
                'rx' => $data['rx'] ?? '0',
                'tx' => $data['tx'] ?? '0',
            ];
            
            $peer['handshake_formatted'] = WireGuardManager::formatHandshake($peer['last-handshake'] ?? '');
            $peer['rx_formatted'] = WireGuardManager::formatBytes((int)($peer['rx'] ?? 0));
            $peer['tx_formatted'] = WireGuardManager::formatBytes((int)($peer['tx'] ?? 0));
            
            $filteredPeers[] = $peer;
        }
        
        return $filteredPeers;
    }
    
    private function queryNativeApi(array $peerNames = [], string $action = 'get_peers', array $extraData = []): array
    {
        $nativeConfig = $this->config['native_api'] ?? [];
        
        $input = [
            'host' => $nativeConfig['host'] ?? $this->config['host'],
            'port' => $nativeConfig['port'] ?? 8728,
            'username' => $nativeConfig['username'] ?? $this->config['username'],
            'password' => $nativeConfig['password'] ?? $this->config['password'],
            'peers' => array_values($peerNames),
            'interface' => $this->getInterface(),
            'tls' => $nativeConfig['tls'] ?? false,
            'action' => $action,
        ];
        
        // Merge extra data (payload, peer_id, update_data, etc.)
        foreach ($extraData as $key => $value) {
            $input[$key] = $value;
        }
        
        $script = $nativeConfig['python_script'] ?? __DIR__ . '/get_peer_data.py';
        
        if (!is_file($script)) {
            throw new Exception("Native API script not found: $script");
        }
        
        // Use proc_open to pass credentials via stdin (secure)
        $cmd = "python3 " . escapeshellarg($script);
        
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];
        
        $proc = proc_open($cmd, $descriptors, $pipes);
        
        if (!is_resource($proc)) {
            error_log("Failed to start Python bridge process");
            return [];
        }
        
        // Set timeout for the process (10 seconds)
        stream_set_timeout($pipes[1], 10);
        stream_set_timeout($pipes[2], 10);
        
        // Write input to stdin
        fwrite($pipes[0], json_encode($input));
        fclose($pipes[0]);
        
        // Read output with timeout
        $stdout = '';
        $stderr = '';
        $startTime = time();
        $timeout = 15; // 15 seconds total timeout
        
        while (!feof($pipes[1]) && (time() - $startTime) < $timeout) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            $ready = stream_select($read, $write, $except, 1); // 1 second chunks
            
            if ($ready === false) {
                break;
            }
            
            foreach ($read as $stream) {
                if ($stream === $pipes[1]) {
                    $stdout .= fread($pipes[1], 8192);
                } elseif ($stream === $pipes[2]) {
                    $stderr .= fread($pipes[2], 8192);
                }
            }
            
            // Check if process has finished
            $status = proc_get_status($proc);
            if (!$status['running']) {
                break;
            }
        }
        
        // If still running after timeout, kill it
        $status = proc_get_status($proc);
        if ($status['running']) {
            proc_terminate($proc, SIGKILL);
            error_log("Python bridge timed out after {$timeout}s, killed process");
        }
        
        // Drain any remaining output
        while (!feof($pipes[1])) {
            $stdout .= fread($pipes[1], 8192);
        }
        while (!feof($pipes[2])) {
            $stderr .= fread($pipes[2], 8192);
        }
        
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $returnCode = proc_close($proc);
        
        if ($returnCode !== 0) {
            // Try to parse error from stdout (where Python bridge outputs JSON errors)
            $errorFromStdout = '';
            $stdoutDecoded = json_decode($stdout, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($stdoutDecoded['error'])) {
                $errorFromStdout = $stdoutDecoded['error'];
            }
            
            $errorMsg = "Python bridge failed (code $returnCode)";
            if (!empty($errorFromStdout)) {
                $errorMsg .= ": $errorFromStdout";
            } elseif (!empty($stderr)) {
                $errorMsg .= ": $stderr";
            }
            error_log($errorMsg);
            throw new Exception($errorMsg);
        }
        
        $result = json_decode($stdout, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Python bridge returned invalid JSON: $stdout");
            return [];
        }
        
        if (isset($result['error'])) {
            error_log("Python bridge error: " . $result['error']);
            throw new Exception("Python bridge error: " . $result['error']);
        }
        
        return $result;
    }
}
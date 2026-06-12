<?php

class MikrotikRestClient {
    private string $baseUrl;
    private string $username;
    private string $password;
    private bool $sslVerify;
    private int $timeout;

    /**
     * MikrotikRestClient constructor.
     * 
     * @param string $host Hostname, IP address or full URL of the MikroTik CHR.
     * @param string $username
     * @param string $password
     * @param bool $sslVerify Whether to verify SSL certificates (default false).
     * @param int $timeout Connection timeout in seconds.
     */
    public function __construct(
        string $host,
        string $username,
        string $password,
        bool $sslVerify = false,
        int $timeout = 10
    ) {
        // Build base URL. If protocol is not specified, default to https://
        if (!str_starts_with($host, 'http://') && !str_starts_with($host, 'https://')) {
            $host = 'https://' . $host;
        }
        
        // Ensure no trailing slash
        $this->baseUrl = rtrim($host, '/');
        if (!str_ends_with($this->baseUrl, '/rest')) {
            $this->baseUrl .= '/rest';
        }

        $this->username = $username;
        $this->password = $password;
        $this->sslVerify = $sslVerify;
        $this->timeout = $timeout;
    }

    /**
     * Send an HTTP request to the RouterOS REST API.
     * 
     * @param string $method HTTP method (GET, POST, PATCH, PUT, DELETE)
     * @param string $path API endpoint path (e.g., '/interface/wireguard/peers')
     * @param array|null $data Payload data to send in the body.
     * @return array Decoded JSON response.
     * @throws Exception on connection failure or API error response.
     */
    public function request(string $method, string $path, array $data = null): array {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $method = strtoupper($method);
        
        $headers = [
            'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password),
            'Accept: application/json',
        ];
        
        $content = '';
        if ($data !== null) {
            $jsonData = json_encode($data);
            if ($jsonData === false) {
                throw new Exception("Failed to encode JSON payload: " . json_last_error_msg());
            }
            $content = $jsonData;
            $headers[] = 'Content-Type: application/json';
        }
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $content,
                'ignore_errors' => true, // Don't throw PHP warnings on 4xx/5xx status codes
                'timeout' => $this->timeout,
            ],
            'ssl' => [
                'verify_peer' => $this->sslVerify,
                'verify_peer_name' => $this->sslVerify,
            ]
        ];
        
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            throw new Exception("Network request failed: " . ($error['message'] ?? 'Unknown connection error') . " - Target: $url");
        }
        
        $httpCode = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $http_response_header[0], $matches)) {
                $httpCode = (int)$matches[1];
            }
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMessage = "API returned HTTP $httpCode";
            if (is_array($decoded)) {
                if (isset($decoded['detail'])) {
                    $errorMessage .= ': ' . $decoded['detail'];
                } elseif (isset($decoded['message'])) {
                    $errorMessage .= ': ' . $decoded['message'];
                }
            } else {
                $errorMessage .= ': ' . substr($response, 0, 200);
            }
            throw new Exception($errorMessage);
        }
        
        return is_array($decoded) ? $decoded : [];
    }
}

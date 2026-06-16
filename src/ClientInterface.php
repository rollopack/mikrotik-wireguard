<?php

interface ClientInterface
{
    /**
     * Send an HTTP request to the RouterOS REST API.
     *
     * @param string $method HTTP method (GET, POST, PATCH, PUT, DELETE)
     * @param string $path API endpoint path (e.g., '/interface/wireguard/peers')
     * @param array|null $data Payload data to send in the body.
     * @return array Decoded JSON response.
     * @throws Exception on connection failure or API error response.
     */
    public function request(string $method, string $path, ?array $data = null): array;

    /**
     * Get all WireGuard peers with full data including last-handshake.
     * Implementations may override this to provide enhanced data (e.g., native API).
     *
     * @return array List of peers with normalized fields.
     * @throws Exception on failure.
     */
    public function getPeers(): array;

    /**
     * Get the server's WireGuard public key.
     *
     * @return string Public key in base64.
     * @throws Exception on failure.
     */
    public function getServerPublicKey(): string;

    /**
     * Get the WireGuard interface name from config.
     *
     * @return string
     */
    public function getInterface(): string;
}
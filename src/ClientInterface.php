<?php

interface ClientInterface
{
    /**
     * Send an HTTP request to the RouterOS REST API.
     * Only used by REST implementation. Native implementation should use specific methods.
     *
     * @param string $method HTTP method (GET, POST, PATCH, PUT, DELETE)
     * @param string $path API endpoint path (e.g., '/interface/wireguard/peers')
     * @param array|null $data Payload data to send in the body.
     * @return array Decoded JSON response.
     * @throws Exception on connection failure or API error response.
     * @deprecated Use specific methods instead (getPeers, addPeer, updatePeer, deletePeer, etc.)
     */
    public function request(string $method, string $path, ?array $data = null): array;

    /**
     * Get all WireGuard peers with full data including last-handshake.
     * Filters by configured interface.
     *
     * @return array List of peers with normalized fields.
     * @throws Exception on failure.
     */
    public function getPeers(): array;

    /**
     * Get ALL WireGuard peers without interface filtering.
     * Used for IP allocation to avoid collisions across interfaces.
     *
     * @return array List of all peers with normalized fields.
     * @throws Exception on failure.
     */
    public function getAllPeers(): array;

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

    /**
     * Add a new WireGuard peer.
     *
     * @param array $payload Peer data (interface, public-key, allowed-address, name)
     * @return array Created peer data including .id
     * @throws Exception on failure.
     */
    public function addPeer(array $payload): array;

    /**
     * Update an existing WireGuard peer.
     *
     * @param string $id Peer ID (e.g., *1c)
     * @param array $payload Update data (name, public-key, etc.)
     * @return void
     * @throws Exception on failure.
     */
    public function updatePeer(string $id, array $payload): void;

    /**
     * Delete a WireGuard peer.
     *
     * @param string $id Peer ID (e.g., *1c)
     * @return void
     * @throws Exception on failure.
     */
    public function deletePeer(string $id): void;

    /**
     * Get PPP secrets (for SSTP/PPTP export).
     *
     * @return array List of PPP secrets
     * @throws Exception on failure.
     */
    public function getPppSecrets(): array;

    /**
     * Get PPP active connections.
     *
     * @return array List of active PPP connections
     * @throws Exception on failure.
     */
    public function getPppActive(): array;
}
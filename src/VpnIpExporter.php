<?php

require_once __DIR__ . '/ClientInterface.php';
require_once __DIR__ . '/WireGuardManager.php';

/**
 * Collects VPN client IPv4 addresses for export.
 *
 * Shared by the dashboard API (src/api.php) and the CLI helper
 * (src/export-vpn-ips.php) so both stay consistent.
 */
class VpnIpExporter
{
    private ClientInterface $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Unique, sorted IPv4 addresses of WireGuard peers.
     *
     * @return string[]
     * @throws Exception on API failure.
     */
    public function wireguardIps(): array
    {
        return WireGuardManager::extractUniqueIpv4Addresses($this->client->getPeers());
    }

    /**
     * Unique, sorted IPv4 addresses belonging to a PPP service (sstp/pptp).
     *
     * Reads /ppp/secret first. When $fallbackToActive is true and the secrets
     * are empty, /ppp/active is used as a fallback (the dashboard needs this
     * for native mode where secrets may not be exposed).
     *
     * Any failure is captured in $error instead of being thrown, so callers
     * can still export the remaining data.
     *
     * @param string $service PPP service name ('sstp' or 'pptp').
     * @param bool $fallbackToActive Query /ppp/active when no secret IPs are found.
     * @param string|null $error Receives the first failure message, if any.
     * @return string[]
     */
    public function serviceIps(string $service, bool $fallbackToActive = false, ?string &$error = null): array
    {
        $ips = [];

        try {
            foreach ($this->client->getPppSecrets() as $secret) {
                if (($secret['service'] ?? '') !== $service) {
                    continue;
                }
                if (WireGuardManager::normalizeBool($secret['disabled'] ?? 'no')) {
                    continue;
                }
                $address = $secret['remote-address'] ?? $secret['address'] ?? '';
                if (self::isIpv4($address)) {
                    $ips[] = $address;
                }
            }
        } catch (Exception $e) {
            if ($error === null) {
                $error = $e->getMessage();
            }
        }

        if ($fallbackToActive && count($ips) === 0 && $error === null) {
            try {
                foreach ($this->client->getPppActive() as $session) {
                    if (($session['service'] ?? '') !== $service) {
                        continue;
                    }
                    $address = $session['address'] ?? $session['remote-address'] ?? '';
                    if (self::isIpv4($address)) {
                        $ips[] = $address;
                    }
                }
            } catch (Exception $e) {
                if ($error === null) {
                    $error = $e->getMessage();
                }
            }
        }

        return self::normalize($ips);
    }

    /**
     * @param mixed $address
     */
    private static function isIpv4($address): bool
    {
        return is_string($address)
            && $address !== ''
            && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * @param string[] $ips
     * @return string[]
     */
    private static function normalize(array $ips): array
    {
        $ips = array_values(array_unique($ips));
        if (count($ips) > 0) {
            usort($ips, static fn(string $a, string $b): int => strcmp(inet_pton($a), inet_pton($b)));
        }
        return $ips;
    }
}

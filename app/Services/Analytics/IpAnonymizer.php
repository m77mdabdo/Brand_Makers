<?php

namespace App\Services\Analytics;

/**
 * Reduces a client IP to something that can be stored.
 *
 * TRUNCATION, NOT HASHING. A hash of an IPv4 address is not actually
 * anonymous — the whole space is 2^32, so any unsalted hash is
 * brute-forceable back to the original in seconds on a laptop, and a
 * salted hash that is not brute-forceable is also useless for the one
 * thing this field is for (noticing that fifty "visitors" came from one
 * office). Truncation is irreversible by construction and keeps coarse
 * locality, which is what GDPR guidance and every analytics vendor's "IP
 * anonymization" actually means.
 *
 * IPv4 drops the last octet (/24, ~256 addresses). IPv6 keeps the first
 * three hextets (/48, the smallest block normally assigned to a single
 * subscriber) — /64 would still identify one household's LAN.
 *
 * The raw address is never stored, logged or returned from here. There is
 * no method on this class that gives it back.
 */
class IpAnonymizer
{
    public function anonymize(?string $ip): ?string
    {
        if ($ip === null || trim($ip) === '') {
            return null;
        }

        $ip = trim($ip);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->truncateIpv4($ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->truncateIpv6($ip);
        }

        // Not an address at all — a spoofed header, a unix socket path, or
        // the literal "unknown" some proxies send. Storing it would defeat
        // the point of this class, so it is dropped.
        return null;
    }

    private function truncateIpv4(string $ip): string
    {
        $parts = explode('.', $ip);
        $parts[3] = '0';

        return implode('.', $parts);
    }

    /**
     * Normalised through inet_pton/inet_ntop so the stored value is the
     * same string whatever notation the client arrived in — "2001:db8::1"
     * and "2001:0db8:0000:0000:0000:0000:0000:0001" must not become two
     * different prefixes.
     */
    private function truncateIpv6(string $ip): ?string
    {
        $packed = inet_pton($ip);

        if ($packed === false) {
            return null;
        }

        // Keep the first 48 bits (6 bytes), zero the remaining 10.
        $result = inet_ntop(substr($packed, 0, 6).str_repeat("\0", 10));

        return $result === false ? null : $result;
    }
}

<?php

namespace App\Support\Network;

/**
 * Whether the server may fetch from an IP address on a user's behalf (SSRF).
 *
 * PHP's private/reserved flags miss addresses that reach internal hosts
 * anyway: IPv4 wrapped in IPv6 (::ffff:127.0.0.1, NAT64 64:ff9b::7f00:1,
 * 6to4 2002:7f00:1::), carrier-grade NAT 100.64.0.0/10 (where some clouds
 * serve instance metadata, e.g. 100.100.100.200), and a few special ranges.
 */
final class PublicAddress
{
    /** IPv4 ranges PHP's flags let through but a server must not fetch from. */
    private const BLOCKED_V4 = [
        '0.0.0.0/8', '100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '198.18.0.0/15',
        '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
    ];

    public static function isPublic(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return self::isPublicV4($ip);
        }

        $packed = @inet_pton($ip);
        if ($packed === false || strlen($packed) !== 16) {
            return false;
        }

        $embedded = self::embeddedV4($packed);
        if ($embedded !== null) {
            return self::isPublicV4($embedded);
        }

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            && ! self::inV6Prefix($packed, "\xff", 8);   // multicast ff00::/8
    }

    private static function isPublicV4(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        $long = ip2long($ip);
        foreach (self::BLOCKED_V4 as $cidr) {
            [$network, $bits] = explode('/', $cidr);
            $mask = -1 << (32 - (int) $bits);
            if (($long & $mask) === (ip2long($network) & $mask)) {
                return false;
            }
        }

        return true;
    }

    /** The IPv4 address an IPv6 address carries, for the forms that route to it. */
    private static function embeddedV4(string $packed): ?string
    {
        $tail = substr($packed, 12, 4);

        // ::ffff:a.b.c.d (mapped) and ::a.b.c.d (deprecated compatible)
        if (substr($packed, 0, 10) === str_repeat("\0", 10) && in_array(substr($packed, 10, 2), ["\xff\xff", "\0\0"], true)) {
            return inet_ntop($tail) ?: null;
        }
        // 64:ff9b::/96 (NAT64)
        if (substr($packed, 0, 12) === "\x00\x64\xff\x9b".str_repeat("\0", 8)) {
            return inet_ntop($tail) ?: null;
        }
        // 2002:aabb:ccdd::/48 (6to4)
        if (substr($packed, 0, 2) === "\x20\x02") {
            return inet_ntop(substr($packed, 2, 4)) ?: null;
        }

        return null;
    }

    private static function inV6Prefix(string $packed, string $prefix, int $bits): bool
    {
        return strncmp($packed, $prefix, intdiv($bits, 8)) === 0;
    }
}

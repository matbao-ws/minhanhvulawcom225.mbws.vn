<?php

namespace Tests\Unit;

use App\Support\Network\PublicAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PublicAddressTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function internalAddresses(): array
    {
        return [
            'loopback' => ['127.0.0.1'],
            'private' => ['10.1.2.3'],
            'link-local metadata' => ['169.254.169.254'],
            'cgnat / alibaba metadata' => ['100.100.100.200'],
            'ietf protocol assignments' => ['192.0.0.170'],
            'benchmarking' => ['198.18.0.1'],
            'multicast' => ['224.0.0.1'],
            'this network' => ['0.0.0.0'],
            'ipv6 loopback' => ['::1'],
            'ipv6 unique local' => ['fd00::1'],
            'ipv4-mapped loopback' => ['::ffff:127.0.0.1'],
            'ipv4-mapped private' => ['::ffff:10.0.0.1'],
            'ipv4-compatible loopback' => ['::127.0.0.1'],
            'nat64 loopback' => ['64:ff9b::7f00:1'],
            '6to4 loopback' => ['2002:7f00:1::1'],
            'ipv6 multicast' => ['ff02::1'],
            'garbage' => ['not-an-ip'],
        ];
    }

    #[DataProvider('internalAddresses')]
    public function test_internal_addresses_are_refused(string $ip): void
    {
        $this->assertFalse(PublicAddress::isPublic($ip));
    }

    public function test_public_addresses_are_allowed(): void
    {
        foreach (['8.8.8.8', '1.1.1.1', '2001:4860:4860::8888', '::ffff:8.8.8.8', '2002:0808:0808::1'] as $ip) {
            $this->assertTrue(PublicAddress::isPublic($ip), $ip);
        }
    }
}

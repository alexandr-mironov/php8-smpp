<?php

namespace Utils\Network;

use Smpp\Utils\Network\Entry;
use Smpp\Utils\Network\Resolver;
use PHPUnit\Framework\TestCase;

class ResolverTest extends TestCase
{
    public function testResolveIPsReturnsIpv4List(): void
    {
        Resolver::setDnsResolver(fn() => [['ip' => '1.1.1.1']]);
        $ips = Resolver::resolveIPs('example.com', DNS_A);
        $this->assertEquals(['1.1.1.1'], $ips);
    }

    public function testCreateFallbackEntryDetectsIpVersion(): void
    {
        $ipv4Entry = Resolver::createFallbackEntry('1.1.1.1', 2775);
        $this->assertNotNull($ipv4Entry->getIpv4());
        $this->assertEquals(new Entry(port: 2775, ipv4: '1.1.1.1'), $ipv4Entry);

        $ipv6Entry = Resolver::createFallbackEntry('::1', 2775);
        $this->assertNotNull($ipv6Entry->getIpv6());
        $this->assertEquals(new Entry(port: 2775, ipv6: '::1'), $ipv6Entry);
    }
}

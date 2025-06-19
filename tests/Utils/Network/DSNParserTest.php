<?php

namespace Utils\Network;

use PHPUnit\Framework\TestCase;
use Smpp\Exceptions\SmppInvalidArgumentException;
use Smpp\Utils\Network\DSNParser;
use Smpp\Utils\Network\Entry;

class DSNParserTest extends TestCase
{
    /**
     * @param string $dsn
     * @param string|null $expectedIPv4
     * @param string|null $expectedIPv6
     * @param int|null $expectedPort
     * @throws SmppInvalidArgumentException
     * @dataProvider dataProviderTestParse
     */
    public function testParse(string $dsn, ?string $expectedIPv4, ?string $expectedIPv6, ?int $expectedPort): void
    {
        /** @var Entry $entry */
        foreach (DSNParser::parse($dsn) as $entry) {
            $this->assertEquals($expectedIPv4, $entry->getIpv4());
            $this->assertEquals($expectedIPv6, $entry->getIpv6());
            $this->assertEquals($expectedPort, $entry->getPort());
        }
    }

    /**
     * @return array<mixed>
     */
    public function dataProviderTestParse(): array
    {
        return [
            ['127.0.0.1:2775', '127.0.0.1', null, 2775],
            ['[::1]:2776', null, '::1', 2776],
        ];
    }

    public function testParseIPv6DSN(): void
    {

    }
}

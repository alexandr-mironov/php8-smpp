<?php

declare(strict_types=1);

namespace Smpp\Utils\Network;

use Generator;
use Smpp\Exceptions\SmppInvalidArgumentException;

/**
 * Parses DSN (Data Source Name) strings for SMPP connections.
 * Handles IPv4, IPv6, and domain names, returning a generator of connection entries.
 */
class DSNParser
{
    /**
     * @param string ...$dsnEntries
     * @return Entry[]
     *
     * @throws SmppInvalidArgumentException
     */
    public static function parseDSNEntries(string ...$dsnEntries): array
    {
        $parsedEntries = [];

        foreach (self::getEntryGenerator($dsnEntries) as $entry) {
            $parsedEntries[] = $entry;
        }

        return $parsedEntries;
    }

    /**
     * @param string[] $dsns
     *
     * @return Generator<int, Entry, mixed, void>
     *   - int: Generator keys (auto-increment)
     *   - Entry: Generated values of type Entry
     *   - mixed: Send type (unused in this generator)
     *   - void: Return type (nothing returned after generation)
     *
     * @throws SmppInvalidArgumentException
     */
    private static function getEntryGenerator(array $dsns): Generator
    {
        foreach ($dsns as $dsn) {
            yield from DSNParser::parse($dsn);
        }
    }

    /**
     * Parses a DSN string and returns a generator of connection entries.
     *
     * @param string $dsn Connection string in "host:port" or "[ipv6]:port" format
     * @return Generator<int, Entry, mixed, void> Generator yielding Entry objects with connection info
     * @throws SmppInvalidArgumentException If DSN format is invalid
     */
    public static function parse(string $dsn): Generator
    {
        // Handle IPv6 format ([ipv6]:port)
        if (str_starts_with($dsn, '[')) {
            [$ip, $port] = self::parseIPv6DSN($dsn);
            yield new Entry(
                port: $port,
                ipv6: $ip
            );
            return;
        }

        // Split into host and port parts
        $parts = explode(':', $dsn, 2);

        if (count($parts) !== 2) {
            throw new SmppInvalidArgumentException(
                'Invalid DSN format. Expected "host:port" or "[ipv6]:port"'
            );
        }

        [$host, $portStr] = $parts;
        $port = self::parsePort((int)$portStr);

        // Handle IPv4 addresses
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            yield new Entry(
                port: $port,
                ipv4: $host
            );
            return;
        }

        // Handle domain names
        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false) {
            yield from Resolver::getIPsByHost($host, $port);
            return;
        }

        // If we reach here, the host format is unrecognized
        throw new SmppInvalidArgumentException(
            'Invalid host format. Expected IPv4, IPv6 or valid domain name.'
        );
    }

    /**
     * Parses an IPv6 DSN string in [ipv6]:port format.
     *
     * @param string $dsn IPv6 connection string
     * @return array{string, int} Array containing IPv6 address and port
     * @throws SmppInvalidArgumentException If format is invalid
     */
    public static function parseIPv6DSN(string $dsn): array
    {
        $parts = explode(']:', $dsn, 2);

        if (count($parts) !== 2) {
            throw new SmppInvalidArgumentException(
                'Invalid IPv6 DSN format. Expected "[ipv6]:port"'
            );
        }

        $ip   = trim($parts[0], "[ \t\n\r\0\x0B");
        $port = self::parsePort((int)$parts[1]);

        // Additional IPv6 validation
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            throw new SmppInvalidArgumentException('Invalid IPv6 address');
        }

        return [$ip, $port];
    }

    /**
     * Validates and normalizes a port number.
     *
     * @param int $port Port number
     * @return int<1, 65535> Normalized port number
     * @throws SmppInvalidArgumentException If port is out of valid range
     */
    public static function parsePort(int $port): int
    {
        if ($port < 1 || $port > 65535) {
            throw new SmppInvalidArgumentException(
                sprintf('Port must be between 1 and 65535, %d given', $port)
            );
        }

        return $port;
    }
}
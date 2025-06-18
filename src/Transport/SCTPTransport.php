<?php

declare(strict_types=1);

namespace Smpp\Transport;

/**
 * Define IPPROTO_SCTP constant if not already defined
 *
 * This ensures compatibility with systems where the constant might not be available
 * while providing static analyzers with the necessary definition.
 */
if (!defined('IPPROTO_SCTP')) {
    define('IPPROTO_SCTP', 132);  // Standard IANA protocol number for SCTP
}

/**
 * SCTP Transport Implementation for SMPP Protocol
 *
 * Extends basic socket transport to use SCTP protocol instead of TCP.
 *
 * ### Requirements:
 * - System must have libsctp installed (e.g. `sudo apt-get install libsctp-dev`)
 * - PHP must be compiled with sockets extension support
 *
 * @package Smpp\Transport
 */
class SCTPTransport extends SocketTransport
{
    /**
     * Protocol type constant (SCTP)
     *
     * @var int
     */
    protected const PROTOCOL_TYPE = IPPROTO_SCTP;
}
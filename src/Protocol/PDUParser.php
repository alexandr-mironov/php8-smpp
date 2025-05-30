<?php

declare(strict_types=1);

namespace Smpp\Protocol;

use Smpp\Exceptions\PDUParseException;
use Smpp\Pdu\PDUHeader;

class PDUParser
{
    public const PDU_HEADER_LENGTH = 16;

    private const UINT32_FORMAT = 'N';
    private const UINT32_BINARY_LENGTH = 4;

    /**
     * SMPP Protocol Data Unit (PDU) Header Structure
     *
     * Offsets and fields according to SMPP v3.4 specification:
     *
     * ┌────────┬──────────────────┬─────────────────────────────────────────────┐
     * │ Offset │ Field            │ Description                                 │
     * ├────────┼──────────────────┼─────────────────────────────────────────────┤
     * │ 0      │ command_length   │ Total length of PDU in octets               │
     * │ 4      │ command_id       │ SMPP command ID                             │
     * │ 8      │ command_status   │ Status of SMPP operation                    │
     * │ 12     │ sequence_number  │ Unique sequence number for PDU exchange     │
     * └────────┴──────────────────┴─────────────────────────────────────────────┘
     *
     * All fields are 4-byte unsigned integers in network byte order (big-endian).
     *
     * @param string $data
     * @return PDUHeader
     * @throws PDUParseException
     */
    public function parseHeader(string $data): PDUHeader
    {
        if (strlen($data) < self::PDU_HEADER_LENGTH) {
            throw new PDUParseException("PDU header must be at least 16 bytes");
        }

        $commandLength = $this->extractUint32($data, 0);
        $commandId = $this->extractUint32($data, 4);
        $commandStatus = $this->extractUint32($data, 8);
        $sequenceNumber = $this->extractUint32($data, 12);

        return new PDUHeader(
            commandLength: $commandLength,
            commandId: $commandId,
            commandStatus: $commandStatus,
            sequenceNumber: $sequenceNumber
        );
    }

    /**
     * @param string $data
     * @param int $offset
     * @return int
     * @throws PDUParseException
     */
    private function extractUint32(string $data, int $offset): int
    {
        $bytes = (string)substr($data, $offset, self::UINT32_BINARY_LENGTH);

        if (strlen($bytes) !== self::UINT32_BINARY_LENGTH) {
            throw new PDUParseException("Not enough bytes for Uint32 at offset $offset");
        }

        /** @var array{1: int}|false $result */
        $result = unpack(self::UINT32_FORMAT, $bytes);

        if ($result === false) {
            throw new PDUParseException("Unexpected unpack() failure");
        }

        return $result[1];
    }
}
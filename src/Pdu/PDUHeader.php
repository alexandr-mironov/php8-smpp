<?php

declare(strict_types=1);

namespace Smpp\Pdu;

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
 */
class PDUHeader
{
    public const PDU_HEADER_LENGTH = 16;

    public function __construct(
        private int $commandLength,
        private int $commandId,
        private int $commandStatus,
        private int $sequenceNumber
    )
    {

    }

    /**
     * @return int
     */
    public function getCommandLength(): int
    {
        return $this->commandLength;
    }

    /**
     * @return int
     */
    public function getCommandId(): int
    {
        return $this->commandId;
    }

    /**
     * @return int
     */
    public function getCommandStatus(): int
    {
        return $this->commandStatus;
    }

    /**
     * @return int
     */
    public function getSequenceNumber(): int
    {
        return $this->sequenceNumber;
    }
}
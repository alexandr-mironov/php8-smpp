<?php

declare(strict_types=1);

namespace smpp;

/**
 * Primitive class for encapsulating PDUs
 * @package smpp
 */
class Pdu
{
    /**
     * Create new generic PDU object
     *
     * @param integer $id
     * @param integer $status
     * @param integer $sequence
     * @param string|null $body
     */
    public function __construct(
        public int $id,
        public int $status,
        public int $sequence,
        public ?string $body
    )
    {
    }
}
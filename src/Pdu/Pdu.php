<?php

declare(strict_types=1);

namespace Smpp\Pdu;

use Smpp\Contracts\Pdu\PduInterface;

/**
 * PDU - is Protocol Data Unit
 * Primitive class for encapsulating PDUs
 */
class Pdu implements PduInterface
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
        protected int $id,
        protected int $status,
        protected int $sequence,
        protected ?string $body
    )
    {
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return int
     */
    public function getSequence(): int
    {
        return $this->sequence;
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        return (string)$this->body;
    }
}
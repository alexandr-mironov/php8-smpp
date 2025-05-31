<?php

declare(strict_types=1);

namespace Smpp\Pdu;


class BinaryPDU
{
    public function __construct(
        private string $data,
        private int $length
    )
    {

    }

    /**
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @return int
     */
    public function getLength(): int
    {
        return $this->length;
    }
}
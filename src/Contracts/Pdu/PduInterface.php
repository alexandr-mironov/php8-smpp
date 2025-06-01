<?php

declare(strict_types=1);


namespace Smpp\Contracts\Pdu;


interface PduInterface
{
    public function getId(): int;
    public function getSequence(): int;
    public function getStatus(): int;
    public function getBody(): string;
}
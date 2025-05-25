<?php

declare(strict_types=1);


namespace Smpp\Contracts\Pdu;


interface PduResponseInterface extends PduInterface
{
    public function isSuccess(): bool;
    public function getMessageId(): ?string;
    public function getError(): ?string;
}
<?php

declare(strict_types=1);


namespace Smpp\Contracts\Pdu;


interface PduInterface
{
    public function getCommandId(): int;
    public function getSequence(): int;
    public function getStatus(): int;
    public function getBody(): string;
    public function toBinary(): string;
    public function getCommandName(): string;
}
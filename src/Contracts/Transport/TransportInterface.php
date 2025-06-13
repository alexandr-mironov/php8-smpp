<?php

declare(strict_types=1);


namespace Smpp\Contracts\Transport;


interface TransportInterface
{
    public function open(): void;

    public function isOpen(): bool;

    public function close(): void;

    public function read(int $length): string;

    public function write(string $data, int $length): void;
}
<?php

declare(strict_types=1);

namespace Smpp\Transport;

use Smpp\Contracts\Transport\ReadStrategyInterface;
use Smpp\Contracts\Transport\RetryableExceptionInterface;
use Socket;

class HybridReadStrategy implements ReadStrategyInterface
{
    public function __construct(
        private ReadStrategyInterface $primary,
        private ReadStrategyInterface $fallback,
    )
    {

    }

    public function read(Socket $socket, int $length): string
    {
        try {
            return $this->primary->read($socket, $length);
        } catch (RetryableExceptionInterface $e) {
            return $this->fallback->read($socket, $length);
        }
    }
}
<?php

declare(strict_types=1);

namespace Smpp\Transport;


use Exception;
use Smpp\Contracts\Transport\ReadStrategyInterface;
use Smpp\Contracts\Transport\RetryableExceptionInterface;
use Smpp\Exceptions\SocketTransportException;
use Socket;

class RetryableReadDecorator implements ReadStrategyInterface
{
    public function __construct(
        private ReadStrategyInterface $strategy,
        private int $maxRetries = 3,
        private int $delayMs = 100,
    )
    {

    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function read(Socket $socket, int $length): string
    {
        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            try {
                return $this->strategy->read($socket, $length);
            } catch (RetryableExceptionInterface $e) {
                if ($attempt === $this->maxRetries - 1) {
                    throw $e;
                }
                usleep($this->delayMs * 1000);
            }
        }

        throw new SocketTransportException('Read operation failed');
    }
}
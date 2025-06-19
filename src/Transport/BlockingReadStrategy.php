<?php

declare(strict_types=1);


namespace Smpp\Transport;


use Smpp\Contracts\Transport\ReadStrategyInterface;
use Smpp\Exceptions\SocketTransportException;
use Socket;

class BlockingReadStrategy implements ReadStrategyInterface
{
    public function __construct(
        private int $timeoutMs
    )
    {

    }

    /**
     * @inheritDoc
     */
    public function read(Socket $socket, int $length): string
    {
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, [
            'sec' => (int)($this->timeoutMs / 1000),
            'usec' => ($this->timeoutMs % 1000) * 1000
        ]);

        $buf = '';
        $received = socket_recv($socket, $buf, $length, 0);

        if ($received === false) {
            throw new SocketTransportException(
                socket_strerror(socket_last_error($socket))
            );
        }

        if ($received === 0) {
            throw new SocketTransportException('Connection closed');
        }

        return $buf;
    }
}
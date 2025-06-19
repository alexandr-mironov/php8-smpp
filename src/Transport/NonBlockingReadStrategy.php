<?php

declare(strict_types=1);


namespace Smpp\Transport;


use Smpp\Contracts\Transport\ReadStrategyInterface;
use Smpp\Exceptions\SocketTemporaryFailureException;
use Smpp\Exceptions\SocketTransportException;
use Socket;

class NonBlockingReadStrategy implements ReadStrategyInterface
{

    /**
     * @inheritDoc
     * @throws SocketTemporaryFailureException
     */
    public function read($socket, int $length): string
    {
        $datagram = "";
        $r        = 0;
        /**
         * @var false|array{sec: int, usec: int} $readTimeout
         */
        $readTimeout = socket_get_option($socket, SOL_SOCKET, SO_RCVTIMEO);
        if ($readTimeout === false) {
            throw new SocketTransportException("Read timeout is not set");
        }

        while ($r < $length) {
            $buf           = '';
            $receivedBytes = socket_recv($socket, $buf, $length - $r, MSG_DONTWAIT);
            if ($receivedBytes === false) {
                $errorNumber = socket_last_error();
                // SOCKET_EWOULDBLOCK has same value (11)
                if ($errorNumber === SOCKET_EAGAIN) {
                    throw new SocketTemporaryFailureException('Resource temporarily unavailable');
                }
                throw new SocketTransportException(
                    'Could not read ' . $length . ' bytes from socket; ' . socket_strerror($errorNumber),
                    $errorNumber
                );
            }
            $r        += $receivedBytes;
            $datagram .= $buf;
            if ($r === $length) {
                return $datagram;
            }

            // wait for data to be available, up to timeout
            $read   = [$socket];
            $write  = null;
            $except = [$socket];

            // check
            if (socket_select($read, $write, $except, $readTimeout['sec'], $readTimeout['usec']) === false) {
                throw new SocketTransportException(
                    'Could not examine socket; ' . socket_strerror(socket_last_error()),
                    socket_last_error()
                );
            }
            /** @var Socket[] $except */
            if (!empty($except)) {
                throw new SocketTransportException(
                    'Socket exception while waiting for data; ' . socket_strerror(socket_last_error()),
                    socket_last_error()
                );
            }
            /** @var Socket[] $read */
            if (empty($read)) {
                throw new SocketTransportException('Timed out waiting for data on socket');
            }
        }

        // for static analyzers
        return $datagram;
    }
}
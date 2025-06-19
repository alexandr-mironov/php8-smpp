<?php

declare(strict_types=1);

namespace Smpp\Transport;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Smpp\Configs\SocketTransportConfig;
use Smpp\Contracts\Transport\TransportInterface;
use Smpp\Exceptions\SocketTransportException;
use Smpp\Utils\Network\Entry;
use Socket;

/**
 * TCP Socket Transport for use with multiple protocols.
 * Supports connection pools and IPv6 in addition to providing a few public methods to make life easier.
 * It's primary purpose is long running connections, since it don't support socket re-use, ip-blacklisting, etc.
 * It assumes a blocking/synchronous architecture, and will block when reading or writing, but will enforce timeouts.
 *
 * Copyright (C) 2011 OnlineCity
 * Licensed under the MIT license, which can be read at: http://www.opensource.org/licenses/mit-license.php
 * @author hd@onlinecity.dk
 */
class SocketTransport implements TransportInterface
{
    protected const PROTOCOL_TYPE = SOL_TCP;
    /**
     * @var LoggerInterface
     */
    public LoggerInterface $logger;
    /**
     * @var Socket instance of Socket (since PHP 8)
     * @see https://www.php.net/manual/ru/class.socket.php
     */
    protected Socket $socket;
    /** @var array<mixed> */
    protected array $hosts;

    /**
     * SocketTransport constructor.
     *
     * @param Entry[] $entries
     * @param SocketTransportConfig $config
     */
    public function __construct(
        private array $entries,
        private SocketTransportConfig $config,
    )
    {
        $this->logger = new NullLogger();

        if ($this->config->isRandomHost()) {
            shuffle($entries);
        }
        $this->entries = $entries;
    }

    /**
     * Get an arbitrary option
     *
     * @param integer $option
     * @param integer $level
     *
     * @return array<mixed, mixed>|false|int
     */
    public function getSocketOption(int $option, int $level = SOL_SOCKET): array|false|int
    {
        return socket_get_option($this->socket, $level, $option);
    }

    /**
     * Sets the send timeout.
     * Returns true on success, or false.
     * @param int $timeout Timeout in milliseconds.
     *
     * @return bool
     */
    public function setSendTimeout(int $timeout): bool
    {
        if (!$this->isOpen()) {
            $this->config->setDefaultSendTimeout($timeout);
            return false;
        } else {
            return $this->setSocketOption(SO_SNDTIMEO, $this->millisecToSolArray($timeout));
        }
    }

    /**
     * Check if the socket is constructed, and there are no exceptions on it
     * Returns false if it's closed.
     * Throws SocketTransportException is state could not be ascertained
     * @return bool
     * @throws SocketTransportException
     */
    public function isOpen(): bool
    {
        if (!isset($this->socket)) {
            return false;
        }

        $readList   = null;
        $writeList  = null;
        $exceptList = [$this->socket];

        if (socket_select($readList, $writeList, $exceptList, 0) === false) {
            throw new SocketTransportException(
                'Could not examine socket; ' . socket_strerror(socket_last_error()),
                socket_last_error()
            );
        }

        // if there is an exception on our socket it's probably dead
        /** @var Socket[] $exceptList */
        if (!empty($exceptList)) {
            return false;
        }

        return true;
    }

    /**
     * Set an arbitrary option
     *
     * @param integer $option
     * @param array<mixed>|int|string $value
     * @param integer $level
     * @return bool
     */
    public function setSocketOption(int $option, mixed $value, int $level = SOL_SOCKET): bool
    {
        return socket_set_option($this->socket, $level, $option, $value);
    }

    /**
     * Convert a milliseconds into a socket sec+usec array
     * @param integer $millisec
     *
     * @return array{sec: false|float, usec: int}
     */
    private function millisecToSolArray(int $millisec): array
    {
        $usec = $millisec * 1000;
        return [
            'sec'  => floor($usec / 1000000),
            'usec' => $usec % 1000000
        ];
    }

    /**
     * Sets the receive timeout.
     * Returns true on success, or false.
     * @param int $timeout Timeout in milliseconds.
     * @return bool
     */
    public function setRecvTimeout(int $timeout): bool
    {
        if (!$this->isOpen()) {
            $this->config->setDefaultRecvTimeout($timeout);
            return false;
        } else {
            return $this->setSocketOption(SO_RCVTIMEO, $this->millisecToSolArray($timeout));
        }
    }

    /**
     * Establishes socket connection using resolved IP entries.
     *
     * Implements multi-step connection strategy:
     * 1. Creates IPv6/IPv4 sockets based on configuration
     * 2. Iterates through available network entries
     * 3. Attempts connections with protocol priority (IPv6 first)
     * 4. Handles socket resources cleanup
     *
     * @throws SocketTransportException When all connection attempts fail
     * @throws RuntimeException On socket creation errors
     */
    public function open(): void
    {
        $sendTimeout    = $this->millisecToSolArray($this->config->getDefaultSendTimeout());
        $receiveTimeout = $this->millisecToSolArray($this->config->getDefaultRecvTimeout());

        // Создаём сокеты только если нужны оба типа адресов
        $socket6 = !$this->config->isForceIpv4()
            ? $this->createSocket(AF_INET6, $sendTimeout, $receiveTimeout)
            : null;

        $socket4 = !$this->config->isForceIpv6()
            ? $this->createSocket(AF_INET, $sendTimeout, $receiveTimeout)
            : null;

        foreach ($this->entries as $entry) {
            // Пробуем IPv6 если есть сокет и адрес
            if ($socket6 && $entry->getIpv6()) {
                if ($this->tryConnect($socket6, $entry->getIpv6(), $entry->getPort())) {
                    if ($socket4) {
                        socket_close($socket4);
                    }
                    $this->socket = $socket6;
                    return;
                }
            }

            // Пробуем IPv4 если есть сокет и адрес
            if ($socket4 && $entry->getIpv4()) {
                if ($this->tryConnect($socket4, $entry->getIpv4(), $entry->getPort())) {
                    if ($socket6) {
                        socket_close($socket6);
                    }
                    $this->socket = $socket4;
                    return;
                }
            }
        }

        throw new SocketTransportException('Could not connect to any of the specified hosts');
    }

    /**
     * Creates and configures network socket.
     *
     * @param int $domain Socket protocol (AF_INET/AF_INET6)
     * @param array{sec: false|float, usec: int} $sendTimeout
     * @param array{sec: false|float, usec: int} $receiveTimeout
     *
     * @return Socket
     * @throws SocketTransportException When socket creation fails
     */
    private function createSocket(int $domain, array $sendTimeout, array $receiveTimeout): Socket
    {
        $socket = @socket_create($domain, SOCK_STREAM, self::PROTOCOL_TYPE);
        if ($socket === false) {
            throw new SocketTransportException(
                'Could not create socket; ' . socket_strerror(socket_last_error()),
                socket_last_error()
            );
        }

        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, $sendTimeout);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, $receiveTimeout);

        return $socket;
    }

    /**
     * Attempts connection to specified network endpoint.
     *
     * Implements:
     * - Error-suppressed connection attempt
     * - Detailed connection logging
     * - Socket resource validation
     *
     * @param Socket $socket Preconfigured socket resource
     * @param string $ip Target IP address
     * @param int $port Destination port
     *
     * @return bool
     */
    private function tryConnect(Socket $socket, string $ip, int $port): bool
    {
        $this->logger->debug("Connecting to $ip:$port...");

        $result = @socket_connect($socket, $ip, $port);
        if ($result) {
            $this->logger->debug("Connected to $ip:$port!");
            return true;
        }

        $this->logger->error(
            "Socket connect to $ip:$port failed; " .
            socket_strerror(socket_last_error())
        );

        return false;
    }

    /**
     * Do a clean shutdown of the socket.
     * Since we don't reuse sockets, we can just close and forget about it,
     * but we choose to wait (linger) for the last data to come through.
     */
    public function close(): void
    {
        socket_set_block($this->socket);
        $this->setSocketOption(SO_LINGER, ['l_onoff' => 1, 'l_linger' => 1]);
        socket_close($this->socket);
    }

    /**
     * Check if there is data waiting for us on the wire
     * @return boolean
     * @throws SocketTransportException
     */
    public function hasData(): bool
    {
        $read   = [$this->socket];
        $write  = null;
        $except = null;
        if (socket_select($read, $write, $except, 0) === false) {
            throw new SocketTransportException(
                'Could not examine socket; ' . socket_strerror(socket_last_error()),
                socket_last_error()
            );
        }

        /** @var Socket[] $read */
        return !empty($read);
    }

    /**
     * Read all the bytes, and block until they are read.
     * Timeout throws SocketTransportException
     *
     * @param int<1,max> $length
     * @return string
     * @throws SocketTransportException
     */
    public function read(int $length): string
    {
        $strategy = $this->config->getReadStrategy();
        return $strategy->read($this->socket, $length);
    }

    /**
     * Write (all) data to the socket.
     * Timeout throws SocketTransportException
     *
     * @param string $buffer
     * @param integer $length
     */
    public function write(string $buffer, int $length): void
    {
        $r = $length;
        /** @var false|array{sec: int, usec: int} $writeTimeout */
        $writeTimeout = socket_get_option($this->socket, SOL_SOCKET, SO_SNDTIMEO);
        if (!$writeTimeout) {
            throw new SocketTransportException('Write timeout is not set');
        }

        while ($r > 0) {
            $wrote = socket_write($this->socket, $buffer, $r);
            if ($wrote === false) {
                throw new SocketTransportException(
                    'Could not write ' . $length . ' bytes to socket; ' . socket_strerror(socket_last_error()),
                    socket_last_error()
                );
            }
            $r -= $wrote;
            if ($r == 0) {
                return;
            }

            $buffer = substr($buffer, $wrote);

            // wait for the socket to accept more data, up to timeout
            $read   = null;
            $write  = [$this->socket];
            $except = [$this->socket];

            // check
            if (socket_select($read, $write, $except, $writeTimeout['sec'], $writeTimeout['usec']) === false) {
                throw new SocketTransportException(
                    'Could not examine socket; ' . socket_strerror(socket_last_error()),
                    socket_last_error()
                );
            }
            /** @var Socket[] $except */
            if (!empty($except)) {
                throw new SocketTransportException(
                    'Socket exception while waiting to write data; ' . socket_strerror(socket_last_error()),
                    socket_last_error()
                );
            }
            /** @var Socket[] $write */
            if (empty($write)) {
                throw new SocketTransportException('Timed out waiting to write data on socket');
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Smpp\Transport;

use ArrayIterator;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Smpp\Configs\SocketTransportConfig;
use Smpp\Contracts\Transport\TransportInterface;
use Smpp\Exceptions\SmppException;
use Smpp\Exceptions\SocketTransportException;
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
    /**
     * @var Socket instance of Socket (since PHP 8)
     * @see https://www.php.net/manual/ru/class.socket.php
     */
    protected Socket $socket;

    /** @var array<mixed> */
    protected array $hosts;

    /**
     * @var int define MSG_DONTWAIT as class const to prevent bug https://bugs.php.net/bug.php?id=48326
     */
    private const MSG_DONTWAIT = 64;

    /**
     * @var SocketTransportConfig
     */
    private SocketTransportConfig $config;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Construct a new socket for this transport to use.
     *
     * @param string[] $hosts list of hosts to try.
     * @param string[]|int|string $ports list of ports to try, or a single common port
     * @param boolean $persist use persistent sockets
     * @param ?LoggerInterface $logger
     * @param SocketTransportConfig|null $config
     */
    public function __construct(
        array $hosts,
        array|int|string $ports,
        protected bool $persist = false,
        ?LoggerInterface $logger = null,
        ?SocketTransportConfig $config = null
    )
    {
        $this->config = ($config === null) ? new SocketTransportConfig() : $config;
        $this->logger = ($logger === null) ? new NullLogger() : $logger;

        // Deal with optional port
        $h = [];
        foreach ($hosts as $key => $host) {
            $h[] = [
                $host,
                is_array($ports) ? $ports[$key] : $ports
            ];
        }
        if ($this->config->isRandomHost()) {
            shuffle($h);
        }
        $this->resolveHosts($h);
    }

    /**
     * Resolve the hostnames into IPs, and sort them into IPv4 or IPv6 groups.
     * If using DNS hostnames, and all lookups fail, a InvalidArgumentException is thrown.
     *
     * @param array<mixed> $hosts
     * @throws InvalidArgumentException
     */
    protected function resolveHosts(array $hosts): void
    {
        $i = 0;
        /**
         * @var array{0: string, 1: int|string} $host
         */
        foreach ($hosts as $host) {
            /**
             * @var string $hostname
             * @var int|string $port
             */
            [$hostname, $port] = $host;
            $ip4s = [];
            $ip6s = [];
            if (preg_match('/^([12]?[0-9]?[0-9]\.){3}([12]?[0-9]?[0-9])$/', $hostname)) {
                // IPv4 address
                $ip4s[] = $hostname;
            } elseif (preg_match('/^([0-9a-f:]+):[0-9a-f]{1,4}$/i', $hostname)) {
                // IPv6 address
                $ip6s[] = $hostname;
            } else { // Do a DNS lookup
                if (!$this->config->isForceIpv4()) {
                    // if not in IPv4 only mode, check the AAAA records first
                    $records = @dns_get_record($hostname, DNS_AAAA);
                    if ($records === false) {
                        $this->logger->error('DNS lookup for AAAA records for: ' . $hostname . ' failed');
                    }
                    if ($records) {
                        foreach ($records as $r) {
                            if (isset($r['ipv6']) && $r['ipv6']) {
                                $ip6s[] = $r['ipv6'];
                            }
                        }
                    }
                    $this->logger->debug("IPv6 addresses for $hostname: " . implode(', ', $ip6s));
                }
                if (!$this->config->isForceIpv6()) {
                    // if not in IPv6 mode check the A records also
                    $records = @dns_get_record($hostname, DNS_A);
                    if ($records === false) {
                        $this->logger->error('DNS lookup for A records for: ' . $hostname . ' failed');
                    }
                    if ($records) {
                        foreach ($records as $r) {
                            if (isset($r['ip']) && $r['ip']) {
                                $ip4s[] = $r['ip'];
                            }
                        }
                    }
                    // also try gethostbyname, since name could also be something else, such as "localhost" etc.
                    $ip = gethostbyname($hostname);
                    if ($ip != $hostname && !in_array($ip, $ip4s)) {
                        $ip4s[] = $ip;
                    }
                    $this->logger->debug("IPv4 addresses for $hostname: " . implode(', ', $ip4s));
                }
            }

            // Did we get any results?
            if ($this->config->isForceIpv4() && empty($ip4s)) {
                continue;
            }
            if ($this->config->isForceIpv6() && empty($ip6s)) {
                continue;
            }
            if (empty($ip4s) && empty($ip6s)) {
                continue;
            }

            $i += count($ip4s) + count($ip6s);

            // Add results to pool
            $this->hosts[] = [$hostname, $port, $ip6s, $ip4s];
        }
        $this->logger->debug(
            "Built connection pool of " . count($this->hosts)
            . " host(s) with " . $i . " ip(s) in total"
        );
        if (empty($this->hosts)) {
            throw new InvalidArgumentException('No valid hosts was found');
        }
    }

    /**
     * Get a reference to the socket.
     * You should use the public functions rather than the socket directly
     *
     * @return Socket
     */
    public function getSocket(): Socket
    {
        return $this->socket;
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
     * Sets the send timeout.
     * Returns true on success, or false.
     * @param int $timeout Timeout in milliseconds.
     * @return bool
     */
    public function setSendTimeout(int $timeout): bool
    {
        if (!$this->isOpen()) {
            $this->config->setDefaultSendTimeout($timeout);
            return false; // todo: check this
        } else {
            return $this->setSocketOption(SO_SNDTIMEO, $this->millisecToSolArray($timeout));
        }
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
            return false; // todo: check this
        } else {
            return $this->setSocketOption(SO_RCVTIMEO, $this->millisecToSolArray($timeout));
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
     * Convert a milliseconds into a socket sec+usec array
     * @param integer $millisec
     *
     * @return array{sec: false|float, usec: int}
     */
    #[ArrayShape(['sec' => "false|float", 'usec' => "int"])]
    private function millisecToSolArray(int $millisec): array
    {
        $usec = $millisec * 1000;
        return [
            'sec'  => floor($usec / 1000000),
            'usec' => $usec % 1000000
        ];
    }

    /**
     * Open the socket, trying to connect to each host in succession.
     * This will prefer IPv6 connections if forceIpv4 is not enabled.
     * If all hosts fail, a SocketTransportException is thrown.
     *
     * @throws SocketTransportException
     */
    public function open(): void
    {
        $sendTimeout    = $this->millisecToSolArray($this->config->getDefaultSendTimeout());
        $receiveTimeout = $this->millisecToSolArray($this->config->getDefaultRecvTimeout());
        if (!$this->config->isForceIpv4()) {
            /** @var Socket|false $socket6 */
            $socket6 = @socket_create(AF_INET6, SOCK_STREAM, SOL_TCP);
            if ($socket6 == false) {
                throw new SocketTransportException(
                    'Could not create socket; ' . socket_strerror(socket_last_error()),
                    socket_last_error()
                );
            }
            socket_set_option($socket6, SOL_SOCKET, SO_SNDTIMEO, $sendTimeout);
            socket_set_option($socket6, SOL_SOCKET, SO_RCVTIMEO, $receiveTimeout);
        }
        if (!$this->config->isForceIpv6()) {
            /** @var Socket|false $socket4 */
            $socket4 = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($socket4 == false) {
                throw new SocketTransportException(
                    'Could not create socket; ' . socket_strerror(socket_last_error()),
                    socket_last_error()
                );
            }
            socket_set_option($socket4, SOL_SOCKET, SO_SNDTIMEO, $sendTimeout);
            socket_set_option($socket4, SOL_SOCKET, SO_RCVTIMEO, $receiveTimeout);
        }
        /**
         * @var array{0: string, 1: int|string, 2: string[], 3: string[]} $host
         */
        foreach ($this->hosts as $host) {
            /**
             * @var int|string $port
             * @var string[] $ip6s
             * @var string[] $ip4s
             */
            [$hostname, $port, $ip6s, $ip4s] = $host;
            if (!$this->config->isForceIpv4() && !empty($ip6s) && isset($socket6)) { // Attempt IPv6s first
                foreach ($ip6s as $ip) {
                    $this->logger->debug("Connecting to $ip:$port...");
                    /** @var Socket $socket6 */
                    $result = @socket_connect($socket6, $ip, (int)$port);
                    if ($result) {
                        $this->logger->debug("Connected to $ip:$port!");
                        if (isset($socket4) && $socket4 instanceof Socket) {
                            @socket_close($socket4);
                        }
                        $this->socket = $socket6;
                        return;
                    } else {
                        $this->logger->error(
                            "Socket connect to $ip:$port failed; "
                            . socket_strerror(socket_last_error())
                        );
                    }
                }
            }
            if (!$this->config->isForceIpv6() && !empty($ip4s) && isset($socket4)) {
                foreach ($ip4s as $ip) {
                    $this->logger->debug("Connecting to $ip:$port...");
                    /** @var Socket $socket4 */
                    $result = @socket_connect($socket4, $ip, (int)$port);
                    if ($result) {
                        $this->logger->debug("Connected to $ip:$port!");
                        if (isset($socket6) && $socket6 instanceof Socket) {
                            @socket_close($socket6);
                        }
                        $this->socket = $socket4;
                        return;
                    } else {
                        $this->logger->error(
                            "Socket connect to $ip:$port failed; "
                            . socket_strerror(socket_last_error())
                        );
                    }
                }
            }
        }
        throw new SocketTransportException('Could not connect to any of the specified hosts');
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
     * Read up to $length bytes from the socket.
     * Does not guarantee that all the bytes are read.
     * Returns false on EOF
     * Returns false on timeout (technically EAGAIN error).
     * Throws SocketTransportException if data could not be read.
     *
     * @param int $length
     * @return false|string
     */
    public function legacyRead(int $length): false|string
    {
        $datagram = socket_read($this->socket, $length, PHP_BINARY_READ);
        // sockets give EAGAIN on timeout
        if ($datagram === false && socket_last_error() === SOCKET_EAGAIN) {
            return false;
        }
        if ($datagram === false) {
            throw new SocketTransportException(
                'Could not read ' . $length . ' bytes from socket; ' . socket_strerror(socket_last_error()),
                socket_last_error()
            );
        }

        return $datagram ?: false;
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
        $datagram = "";
        $r        = 0;
        /**
         * @var false|array{sec: int, usec: int} $readTimeout
         */
        $readTimeout = socket_get_option($this->socket, SOL_SOCKET, SO_RCVTIMEO);
        if ($readTimeout === false) {
            throw new SocketTransportException("Read timeout is not set");
        }

        while ($r < $length) {
            $buf           = '';
            $receivedBytes = socket_recv($this->socket, $buf, $length - $r, self::MSG_DONTWAIT);
            if ($receivedBytes === false) {
                throw new SocketTransportException(
                    'Could not read ' . $length . ' bytes from socket; ' . socket_strerror(socket_last_error()),
                    socket_last_error()
                );
            }
            $r        += $receivedBytes;
            $datagram .= $buf;
            if ($r === $length) {
                return $datagram;
            }

            // wait for data to be available, up to timeout
            $read   = [$this->socket];
            $write  = null;
            $except = [$this->socket];

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

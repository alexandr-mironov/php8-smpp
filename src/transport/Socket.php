<?php

declare(strict_types=1);

namespace smpp\transport;

use InvalidArgumentException;
use smpp\exceptions\SmppException;
use smpp\exceptions\SocketTransportException;
use smpp\HostCollection;
use smpp\LoggerDecorator;
use smpp\LoggerInterface;
use Socket as SocketClass;

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
class Socket
{
    /**
     * @var SocketClass|null $socket - instance of Socket (since PHP 8)
     * @see https://www.php.net/manual/ru/class.socket.php
     */
    protected ?SocketClass $socket = null;

    /** @var array<mixed> */
    protected array $hosts;

    /** @var bool */
    public bool $debug;

    /** @var int */
    protected static int $defaultSendTimeout = 100;

    /** @var int */
    protected static int $defaultRecvTimeout = 750;

    /** @var bool */
    public static bool $defaultDebug = false;

    /** @var bool */
    public static bool $forceIpv6 = false;

    /** @var bool */
    public static bool $forceIpv4 = false;

    /** @var bool */
    public static bool $randomHost = false;

    /** @var HostCollection */
    protected HostCollection $hostCollection;

    /** @var int define MSG_DONTWAIT as class const to prevent bug https://bugs.php.net/bug.php?id=48326 */
    private const MSG_DONTWAIT = 64;
    /**
     * @var LoggerDecorator
     */
    private LoggerDecorator $logger;

    /**
     * Construct a new socket for this transport to use.
     *
     * @param string[] $hosts list of hosts to try.
     * @param string[]|int|string $ports list of ports to try, or a single common port
     * @param boolean $persist use persistent sockets
     * @param LoggerInterface ...$loggers
     */
    public function __construct(
        array $hosts,
        array|int|string $ports,
        protected bool $persist = false,
        LoggerInterface ...$loggers
    )
    {
        // todo: find better solution to provide debug flag into loggers
        LoggerDecorator::$debug = self::$defaultDebug;
        $this->logger = new LoggerDecorator(...$loggers);

        // Deal with optional port
        $this->hostCollection = new HostCollection();
        $h = [];
        foreach ($hosts as $key => $host) {
            $h[] = [
                $host,
                is_array($ports) ? $ports[$key] : $ports
            ];
        }
        if (self::$randomHost) {
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
        foreach ($hosts as $host) {
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
                if (!self::$forceIpv4) {
                    // if not in IPv4 only mode, check the AAAA records first
                    $records = dns_get_record($hostname, DNS_AAAA);
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
                    $this->logger->info("IPv6 addresses for $hostname: " . implode(', ', $ip6s));
                }
                if (!self::$forceIpv6) {
                    // if not in IPv6 mode check the A records also
                    $records = dns_get_record($hostname, DNS_A);
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
                    $this->logger->info("IPv4 addresses for $hostname: " . implode(', ', $ip4s));
                }
            }

            // Did we get any results?
            if (self::$forceIpv4 && empty($ip4s)) {
                continue;
            }
            if (self::$forceIpv6 && empty($ip6s)) {
                continue;
            }
            if (empty($ip4s) && empty($ip6s)) {
                continue;
            }

            $i += count($ip4s) + count($ip6s);

            // Add results to pool
            $this->hosts[] = [$hostname, $port, $ip6s, $ip4s];
        }
        $this->logger->info(
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
     * @return SocketClass
     */
    public function getSocket(): SocketClass
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
     * @param mixed $value
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
            self::$defaultSendTimeout = $timeout;
            return false; // todo: check this
        } else {
            return socket_set_option(
                $this->socket,
                SOL_SOCKET,
                SO_SNDTIMEO,
                $this->millisecToSolArray($timeout)
            );
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
            self::$defaultRecvTimeout = $timeout;
            return false; // todo: check this
        } else {
            return socket_set_option(
                $this->socket,
                SOL_SOCKET,
                SO_RCVTIMEO,
                $this->millisecToSolArray($timeout)
            );
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
        if (!$this->socket instanceof SocketClass) {
            return false;
        }

        $r = null;
        $w = null;
        $e = [$this->socket];

        if (socket_select($r, $w, $e, 0) === false) {
            throw new SocketTransportException(
                'Could not examine socket; ' . socket_strerror(socket_last_error()),
                socket_last_error()
            );
        }

        // if there is an exception on our socket it's probably dead
        /** @var SocketClass[] $e */
        if (!empty($e)) {
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
    private function millisecToSolArray(int $millisec): array
    {
        $usec = $millisec * 1000;
        return [
            'sec' => floor($usec / 1000000),
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
        $sendTimeout = $this->millisecToSolArray(self::$defaultSendTimeout);
        $receiveTimeout = $this->millisecToSolArray(self::$defaultRecvTimeout);
        if (!self::$forceIpv4) {
            /** @var SocketClass|false $socket6 */
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
        if (!self::$forceIpv6) {
            /** @var SocketClass|false $socket4 */
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
        $it = new \ArrayIterator($this->hosts);
        while ($it->valid()) {
            [$hostname, $port, $ip6s, $ip4s] = $it->current();
            if (!self::$forceIpv4 && !empty($ip6s)) { // Attempt IPv6s first
                foreach ($ip6s as $ip) {
                    $this->logger->info("Connecting to $ip:$port...");
                    $result = @socket_connect($socket6, $ip, $port);
                    if ($result) {
                        $this->logger->info("Connected to $ip:$port!");
                        if (isset($socket4) && $socket4 instanceof SocketClass) {
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
            if (!self::$forceIpv6 && !empty($ip4s)) {
                foreach ($ip4s as $ip) {
                    $this->logger->info("Connecting to $ip:$port...");
                    $result = @socket_connect($socket4, $ip, $port);
                    if ($result) {
                        $this->logger->info("Connected to $ip:$port!");
                        if (isset($socket6) && $socket6 instanceof SocketClass) {
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
            $it->next();
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
        socket_set_option($this->socket, SOL_SOCKET, SO_LINGER, ['l_onoff' => 1, 'l_linger' => 1]);
        socket_close($this->socket);
    }

    /**
     * Check if there is data waiting for us on the wire
     * @return boolean
     * @throws SocketTransportException
     */
    public function hasData(): bool
    {
        $r = [$this->socket];
        $w = null;
        $e = null;
        if (socket_select($r, $w, $e, 0) === false) {
            throw new SocketTransportException(
                'Could not examine socket; ' . socket_strerror(socket_last_error()),
                socket_last_error()
            );
        }

        /** @var SocketClass[] $r */
        return !empty($r);
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
    public function read(int $length): false|string
    {
        $d = socket_read($this->socket, $length, PHP_BINARY_READ);
        // sockets give EAGAIN on timeout
        if ($d === false && socket_last_error() === SOCKET_EAGAIN) {
            return false;
        }
        if ($d === false) {
            throw new SocketTransportException(
                'Could not read ' . $length . ' bytes from socket; ' . socket_strerror(socket_last_error()),
                socket_last_error()
            );
        }

        return $d ?: false;
    }

    /**
     * Read all the bytes, and block until they are read.
     * Timeout throws SocketTransportException
     *
     * @param int $length
     * @return string
     */
    public function readAll(int $length): string
    {
        $d = "";
        $r = 0;
        /** @var array{sec: int, usec: int} $readTimeout */
        $readTimeout = socket_get_option($this->socket, SOL_SOCKET, SO_RCVTIMEO);
        while ($r < $length) {
            $buf = '';
            $receivedBytes = socket_recv($this->socket, $buf, $length - $r, self::MSG_DONTWAIT);
            if ($receivedBytes === false) {
                throw new SocketTransportException(
                    'Could not read ' . $length . ' bytes from socket; ' . socket_strerror(socket_last_error()),
                    socket_last_error()
                );
            }
            $r += $receivedBytes;
            $d .= $buf;
            if ($r == $length) {
                return $d;
            }

            // wait for data to be available, up to timeout
            $read = [$this->socket];
            $write = null;
            $except = [$this->socket];

            // check
            if (socket_select($read, $write, $except, $readTimeout['sec'], $readTimeout['usec']) === false) {
                throw new SocketTransportException(
                    'Could not examine socket; ' . socket_strerror(socket_last_error()),
                    socket_last_error()
                );
            }
            /** @var SocketClass[] $except */
            if (!empty($except)) {
                throw new SocketTransportException(
                    'Socket exception while waiting for data; ' . socket_strerror(socket_last_error()),
                    socket_last_error()
                );
            }
            /** @var SocketClass[] $read */
            if (empty($read)) {
                throw new SocketTransportException('Timed out waiting for data on socket');
            }
        }
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
        /** @var array{sec: int, usec: int} $writeTimeout */
        $writeTimeout = socket_get_option($this->socket, SOL_SOCKET, SO_SNDTIMEO);
        if (!$writeTimeout) {
            throw new SmppException(); // todo: replace exception, add exception message
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
            $read = null;
            $write = [$this->socket];
            $except = [$this->socket];

            // check
            if (socket_select($read, $write, $except, $writeTimeout['sec'], $writeTimeout['usec']) === false) {
                throw new SocketTransportException(
                    'Could not examine socket; ' . socket_strerror(socket_last_error()),
                    socket_last_error()
                );
            }
            /** @var SocketClass[] $except */
            if (!empty($except)) {
                throw new SocketTransportException(
                    'Socket exception while waiting to write data; ' . socket_strerror(socket_last_error()),
                    socket_last_error()
                );
            }
            /** @var SocketClass[] $write */
            if (empty($write)) {
                throw new SocketTransportException('Timed out waiting to write data on socket');
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Smpp\Contracts\Transport;

/**
 * Defines fundamental operations for bidirectional byte stream communication.
 *
 * Serves as abstraction layer for SMPP protocol transmission requirements.
 */
interface TransportInterface
{
    /**
     * Initiates communication channel establishment.
     *
     * Implementation must ensure the channel becomes operational
     * or throw appropriate exception on failure.
     */
    public function open(): void;

    /**
     * Determines if communication channel is operational.
     */
    public function isOpen(): bool;

    /**
     * Terminates communication channel and releases resources.
     *
     * Should maintain idempotency - multiple calls must not cause errors.
     */
    public function close(): void;

    /**
     * Retrieves data from the communication channel.
     *
     * @param int $length Exact number of bytes to retrieve
     * @return string
     */
    public function read(int $length): string;

    /**
     * Transmits data through the communication channel.
     *
     * @param string $data
     * @param int $length
     */
    public function write(string $data, int $length): void;

    /**
     * Checks for immediately available data without blocking.
     *
     * Useful for non-blocking I/O patterns. Does not guarantee
     * subsequent read() won't block when returning true.
     */
    public function hasData(): bool;
}
<?php

declare(strict_types=1);

namespace smpp;

/**
 * Class Host
 * @package smpp
 */
class Host implements ItemInterface
{
    /**
     * Host constructor.
     * @param string $host
     * @param int|null $port
     */
    public function __construct(
        private string $host,
        private ?int $port = null,
    )
    {}
}
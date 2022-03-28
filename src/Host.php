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
        public string $host,
        public ?int   $port = null,
    )
    {
    }
}
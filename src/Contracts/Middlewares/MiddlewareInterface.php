<?php

declare(strict_types=1);


namespace Smpp\Contracts\Middlewares;


use Smpp\Contracts\Pdu\PduInterface;
use Smpp\Contracts\Pdu\PduResponseInterface;

interface MiddlewareInterface
{
    public function handle(
        PduInterface $pdu,
        callable $next
    ): PduResponseInterface;
}
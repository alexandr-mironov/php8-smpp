<?php

declare(strict_types=1);

namespace Smpp\Contracts\Client;

use Smpp\Contracts\Middlewares\MiddlewareInterface;
use Smpp\Contracts\Pdu\PduInterface;
use Smpp\Contracts\Pdu\PduResponseInterface;

interface SmppClientInterface
{
    public function send(PduInterface $pdu): PduResponseInterface;
    public function addMiddleware(MiddlewareInterface $middleware): void;
}
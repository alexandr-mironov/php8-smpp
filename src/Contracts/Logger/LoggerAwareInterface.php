<?php

declare(strict_types=1);

namespace Smpp\Contracts\Logger;

use Psr\Log\LoggerInterface;

interface LoggerAwareInterface
{
    public function setLogger(LoggerInterface $logger): void;
    public function getLogger(): ?LoggerInterface;
}
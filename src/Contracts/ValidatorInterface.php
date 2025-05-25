<?php

declare(strict_types=1);


namespace Smpp\Contracts;

use Smpp\Exceptions\SmppException;

interface ValidatorInterface
{
    public function isValid(mixed $value): ?SmppException;
}
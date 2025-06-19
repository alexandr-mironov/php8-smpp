<?php

declare(strict_types=1);

namespace Smpp\Exceptions;

use Smpp\Contracts\Transport\RetryableExceptionInterface;

class SocketTemporaryFailureException extends SmppException implements RetryableExceptionInterface
{

}
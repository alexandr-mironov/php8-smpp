<?php

declare(strict_types=1);


namespace Smpp\Exceptions;


use Smpp\Contracts\Transport\RetryableExceptionInterface;

class SocketTimeoutException extends SmppException implements RetryableExceptionInterface
{

}
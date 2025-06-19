<?php

declare(strict_types=1);


namespace Smpp\Contracts\Transport;


use Socket;

interface ReadStrategyInterface
{
    /**
     * @param Socket $socket
     * @param int $length
     *
     * @return string
     */
    public function read(Socket $socket, int $length): string;
}
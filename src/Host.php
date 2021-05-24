<?php


namespace smpp;


class Host implements ItemInterface
{

    public function __construct(
        private string $host,
        private ?int $port = null,
    )
    {

    }
}
<?php

/**
 * Primitive class for encapsulating PDUs
 * @author hd@onlinecity.dk
 */
namespace smpp;


class Pdu
{
    /**
     * Create new generic PDU object
     *
     * @param integer $id
     * @param integer $status
     * @param integer $sequence
     * @param string $body
     */
    public function __construct(
        public int      $id,
        public int      $status,
        public int      $sequence,
        public string   $body
    )
    {}
}
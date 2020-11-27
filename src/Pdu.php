<?php

/**
 * Primitive class for encapsulating PDUs
 * @author hd@onlinecity.dk
 */
namespace smpp;


class Pdu
{
    public int $id;
    public int $status;
    public int $sequence;
    public string $body;

    /**
     * Create new generic PDU object
     *
     * @param integer $id
     * @param integer $status
     * @param integer $sequence
     * @param string $body
     */
    public function __construct(
        public int $id,
        public int $status,
        public int $sequence,
        public string $body
    )
    {
//        $this->id = $id;
//        $this->status = $status;
//        $this->sequence = $sequence;
//        $this->body = $body;
    }
}
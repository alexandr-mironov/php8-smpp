<?php

declare(strict_types=1);

namespace Smpp\Protocol;

use Psr\Log\LoggerInterface;
use Smpp\Pdu;
use Smpp\Pdu\BinaryPDU;
use Smpp\Pdu\PDUHeader;

class PDUBuilder
{
    public function __construct(
        private LoggerInterface $logger
    )
    {

    }

    /**
     * @param Pdu $pdu
     * @param int $length
     * @return string
     */
    public function packHeader(Pdu $pdu, int $length): string
    {
        return pack("NNNN", $length, $pdu->getId(), $pdu->getStatus(), $pdu->getSequence());
    }

    /**
     * @param Pdu $pdu
     * @return BinaryPDU
     */
    public function packPdu(Pdu $pdu): BinaryPDU
    {
        $length = strlen($pdu->getBody()) + PDUHeader::PDU_HEADER_LENGTH;

        $datagram = $this->packHeader($pdu, $length) . $pdu->getBody();

        $this->logger->debug("Read PDU         : $length bytes");
        $this->logger->debug(' ' . chunk_split(bin2hex($datagram), 2, " "));
        $this->logger->debug(' command_id      : 0x' . dechex($pdu->getId()));
        $this->logger->debug(' sequence number : ' . $pdu->getSequence());

        return new BinaryPDU(
            data: $datagram,
            length: $length
        );
    }
}
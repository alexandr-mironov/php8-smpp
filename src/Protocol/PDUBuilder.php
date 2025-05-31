<?php

declare(strict_types=1);


namespace Smpp\Protocol;


use Psr\Log\LoggerInterface;
use Smpp\Exceptions\SmppException;
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
     * @throws SmppException
     */
    public function packHeader(Pdu $pdu, int $length): string
    {
        $header = pack("NNNN", $length, $pdu->id, $pdu->status, $pdu->sequence);

        if ($header === false) {
            throw new SmppException('');
        }

        return $header;
    }

    /**
     * @param Pdu $pdu
     * @return BinaryPDU
     * @throws SmppException
     */
    public function packPdu(Pdu $pdu): BinaryPDU
    {
        $length = strlen($pdu->body) + PDUHeader::PDU_HEADER_LENGTH;

        $datagram = $this->packHeader($pdu, $length) . $pdu->body;

        $this->logger->debug("Read PDU         : $length bytes");
        $this->logger->debug(' ' . chunk_split(bin2hex($datagram), 2, " "));
        $this->logger->debug(' command_id      : 0x' . dechex($pdu->id));
        $this->logger->debug(' sequence number : ' . $pdu->sequence);

        return new BinaryPDU(
            data: $datagram,
            length: $length
        );
    }
}
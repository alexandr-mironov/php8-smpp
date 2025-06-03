<?php

declare(strict_types=1);

namespace Smpp\Protocol;

use Psr\Log\LoggerInterface;
use Smpp\Exceptions\PDUParseException;
use Smpp\Exceptions\SmppException;
use Smpp\Exceptions\SmppInvalidArgumentException;
use Smpp\Pdu\Address;
use Smpp\Pdu\DeliveryReceipt;
use Smpp\Pdu\Pdu;
use Smpp\Pdu\PDUHeader;
use Smpp\Pdu\Sms;
use Smpp\Pdu\Tag;
use Smpp\Smpp;

class PDUParser
{
    private const UINT32_FORMAT        = 'N';
    private const UINT32_BINARY_LENGTH = 4;

    public function __construct(
        private LoggerInterface $logger
    )
    {

    }

    /**
     * SMPP Protocol Data Unit (PDU) Header Structure
     *
     * Offsets and fields according to SMPP v3.4 specification:
     *
     * ┌────────┬──────────────────┬─────────────────────────────────────────────┐
     * │ Offset │ Field            │ Description                                 │
     * ├────────┼──────────────────┼─────────────────────────────────────────────┤
     * │ 0      │ command_length   │ Total length of PDU in octets               │
     * │ 4      │ command_id       │ SMPP command ID                             │
     * │ 8      │ command_status   │ Status of SMPP operation                    │
     * │ 12     │ sequence_number  │ Unique sequence number for PDU exchange     │
     * └────────┴──────────────────┴─────────────────────────────────────────────┘
     *
     * All fields are 4-byte unsigned integers in network byte order (big-endian).
     *
     * @param string $data
     * @return PDUHeader
     * @throws PDUParseException
     */
    public function parsePduHeader(string $data): PDUHeader
    {
        if (strlen($data) < PDUHeader::PDU_HEADER_LENGTH) {
            throw new PDUParseException("PDU header must be at least 16 bytes");
        }

        $commandLength  = $this->extractUint32($data, 0);
        $commandId      = $this->extractUint32($data, 4);
        $commandStatus  = $this->extractUint32($data, 8);
        $sequenceNumber = $this->extractUint32($data, 12);

        return new PDUHeader(
            commandLength: $commandLength,
            commandId: $commandId,
            commandStatus: $commandStatus,
            sequenceNumber: $sequenceNumber
        );
    }

    /**
     * @param string $data
     * @param int $offset
     * @return int
     * @throws PDUParseException
     */
    private function extractUint32(string $data, int $offset): int
    {
        $bytes = (string)substr($data, $offset, self::UINT32_BINARY_LENGTH);

        if (strlen($bytes) !== self::UINT32_BINARY_LENGTH) {
            throw new PDUParseException("Not enough bytes for Uint32 at offset $offset");
        }

        /** @var array{1: int}|false $result */
        $result = unpack(self::UINT32_FORMAT, $bytes);

        if ($result === false) {
            throw new PDUParseException("Unexpected unpack() failure");
        }

        return $result[1];
    }

    /**
     * @param Pdu $pdu
     * @return Sms
     * @throws SmppException
     * @throws SmppInvalidArgumentException
     */
    public function parseSms(Pdu $pdu): Sms
    {
        // Unpack PDU
        $unpackedElements = unpack("C*", $pdu->getBody());

        if (!$unpackedElements) {
            throw new SmppException('Format not matches with PDU body contents');
        }

        // Read mandatory params
        $serviceType = $this->getString($unpackedElements, 6, true);

        //
        $sourceAddressNumberType             = next($unpackedElements);
        $sourceAddressNumberingPlanIndicator = next($unpackedElements);
        $sourceAddress                       = $this->getString($unpackedElements, 21);
        $source                              = new Address($sourceAddress, $sourceAddressNumberType, $sourceAddressNumberingPlanIndicator);

        //
        $destinationAddrTon = next($unpackedElements);
        $destinationAddrNPI = next($unpackedElements);
        $destinationAddr    = $this->getString($unpackedElements, 21);
        $destination        = new Address($destinationAddr, $destinationAddrTon, $destinationAddrNPI);

        $esmClass     = next($unpackedElements);
        $protocolId   = next($unpackedElements);
        $priorityFlag = next($unpackedElements);
        next($unpackedElements); // schedule_delivery_time
        next($unpackedElements); // validity_period
        $registeredDelivery = next($unpackedElements);
        next($unpackedElements); // replace_if_present_flag
        $dataCoding = next($unpackedElements);
        next($unpackedElements); // sm_default_msg_id
        $sm_length = next($unpackedElements);
        $message   = $this->getString($unpackedElements, $sm_length);

        $tags = [];

        // Check for optional params, and parse them
        if (current($unpackedElements) !== false) {
            do {
                $tag = $this->parseTag($unpackedElements);
                if ($tag !== false) {
                    $tags[] = $tag;
                }
            } while (current($unpackedElements) !== false);
        }

        if (($esmClass & Smpp::ESM_DELIVER_SMSC_RECEIPT) != 0) {
            $sms = new DeliveryReceipt(
                $pdu->getId(),
                $pdu->getStatus(),
                $pdu->getSequence(),
                $pdu->getBody(),
                $serviceType,
                $source,
                $destination,
                $esmClass,
                $protocolId,
                $priorityFlag,
                $registeredDelivery,
                $dataCoding,
                $message,
                $tags
            );
            $sms->parseDeliveryReceipt();
        } else {
            $sms = new Sms(
                $pdu->getId(),
                $pdu->getStatus(),
                $pdu->getSequence(),
                $pdu->getBody(),
                $serviceType,
                $source,
                $destination,
                $esmClass,
                $protocolId,
                $priorityFlag,
                $registeredDelivery,
                $dataCoding,
                $message,
                $tags
            );
        }

        return $sms;
    }

    /**
     * Reads C style null padded string from the char array.
     * Reads until $maxlen or null byte.
     *
     * @param array<mixed> $ar - input array
     * @param integer $maxLength - maximum length to read.
     * @param boolean $firstRead - is this the first bytes read from array?
     * @return string.
     */
    private function getString(array &$ar, int $maxLength = 255, bool $firstRead = false): string
    {
        $string = "";
        $i      = 0;
        do {
            $asciiCode = (int)(($firstRead && $i == 0) ? current($ar) : next($ar));
            if ($asciiCode != 0) {
                $string .= chr($asciiCode);
            }
            $i++;
        } while ($i < $maxLength && $asciiCode != 0);
        return $string;
    }

    /**
     * @param array<mixed> $ar
     * @return false|Tag
     * @throws SmppException
     */
    private function parseTag(array &$ar): false|Tag
    {
        $unpackedData = unpack(
            'nid/nlength',
            pack("C2C2", next($ar), next($ar), next($ar), next($ar))
        );

        if (!$unpackedData) {
            throw new SmppException('Could not read tag data');
        }
        /**
         * Extraction create variables:
         * @var $length
         * @var $id
         */
        extract($unpackedData);

        // Sometimes SMSC return an extra null byte at the end
        if (!isset($id, $length) || ($length == 0 && $id == 0)) {
            return false;
        }

        $value = $this->getOctets($ar, $length);
        $tag   = new Tag(
            id: $id,
            value: $value,
            length: $length
        );

        $this->logger->debug("Parsed tag:");
        $this->logger->debug(" id     :0x" . dechex($tag->getId()));
        $this->logger->debug(" length :" . $tag->getLength());
        $this->logger->debug(" value  :" . chunk_split(bin2hex((string)$tag->getValue()), 2, " "));

        return $tag;
    }

    /**
     * Read a specific number of octets from the char array.
     * Does not stop at null byte
     *
     * @param array<mixed> $ar - input array
     * @param int $length
     * @return string
     */
    private function getOctets(array &$ar, int $length): string
    {
        $string = "";
        for ($i = 0; $i < $length; $i++) {
            $asciiCode = (int)next($ar);
            if ($asciiCode === false) {
                return $string;
            }
            $string .= chr($asciiCode);
        }
        return $string;
    }
}
<?php

declare(strict_types=1);

namespace smpp;

use DateInterval;
use DateTime;
use Exception;
use smpp\exceptions\ClosedTransportException;
use smpp\exceptions\SmppException;
use smpp\exceptions\SmppInvalidArgumentException;
use smpp\exceptions\SocketTransportException;
use smpp\transport\Socket;

/**
 * Class for receiving or sending sms through SMPP protocol.
 * This is a reduced implementation of the SMPP protocol, and as such not all features will or ought to be available.
 * The purpose is to create a lightweight and simplified SMPP client.
 *
 * @author hd@onlinecity.dk, paladin, Alexandr Mironov
 * @see http://en.wikipedia.org/wiki/Short_message_peer-to-peer_protocol - SMPP 3.4 protocol specification
 * Derived from work done by paladin, see: http://sourceforge.net/projects/phpsmppapi/
 *
 * Copyright (C) 2020 Alexandr Mironov
 * Copyright (C) 2011 OnlineCity
 * Copyright (C) 2006 Paladin
 *
 * This library is free software; you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Lesser General Public License for more details.
 *
 * This license can be read at: http://www.opensource.org/licenses/lgpl-2.1.php
 */
class Client
{
    // Available modes
    /** @var string */
    public const MODE_TRANSMITTER = 'transmitter';

    /** @var string */
    public const MODE_TRANSCEIVER = 'transceiver';

    /** @var string */
    public const MODE_RECEIVER = 'receiver';

    /** @var integer Use sar_msg_ref_num and sar_total_segments with 16 bit tags */
    public const CSMS_16BIT_TAGS = 0;

    /** @var integer Use message payload for CSMS */
    public const CSMS_PAYLOAD = 1;

    /** @var integer Embed a UDH in the message with 8-bit reference. */
    public const CSMS_8BIT_UDH = 2;

    // SMPP bind parameters
    /** @var string */
    public static string $systemType = "WWW";

    /** @var int */
    public static int $interfaceVersion = 0x34;

    /** @var int */
    public static int $addrTon = 0;

    /** @var int */
    public static int $addrNPI = 0;

    /** @var string */
    public static string $addressRange = "";

    // ESME transmitter parameters
    /** @var string */
    public static string $smsServiceType = "";

    /** @var int */
    public static int $smsEsmClass = 0x00;

    /** @var int */
    public static int $smsProtocolID = 0x00;

    /** @var int */
    public static int $smsPriorityFlag = 0x00;

    /** @var int */
    public static int $smsRegisteredDeliveryFlag = 0x00;

    /** @var int */
    public static int $smsReplaceIfPresentFlag = 0x00;

    /** @var int */
    public static int $smsSmDefaultMessageID = 0x00;

    /**
     * SMPP v3.4 says octet string are "not necessarily NULL terminated".
     * Switch to toggle this feature
     * @var boolean
     *
     * set NULL terminate octetstrings FALSE as default
     */
    public static bool $smsNullTerminateOctetstrings = false;

    /** @var int */
    public static int $csmsMethod = self::CSMS_16BIT_TAGS;

    /** @var Pdu[] */
    protected array $pduQueue = [];

    // Used for reconnect
    /** @var string */
    protected string $mode;

    /** @var string $login Login of SMPP gateway */
    private string $login = '';

    /** @var string $pass Password of SMPP gateway */
    private string $pass = '';

    /** @var int */
    protected int $sequenceNumber = 1;

    /** @var int */
    protected int $sarMessageReferenceNumber;

    /** @var LoggerDecorator */
    public LoggerDecorator $logger;

    /**
     * Construct the SMPP class
     *
     * @param Socket $transport
     * @param LoggerInterface ...$loggers
     */
    public function __construct(
        public Socket $transport,
        LoggerInterface ...$loggers
    )
    {
        LoggerDecorator::$debug = Socket::$defaultDebug;
        $this->logger = new LoggerDecorator(...$loggers);
    }

    /**
     * Binds the receiver. One object can be bound only as receiver or only as transmitter.
     * @param string $login - ESME system_id
     * @param string $pass - ESME password
     *
     * @return void
     *
     * @throws SmppException
     * @throws ClosedTransportException
     * @throws Exception
     */
    public function bindReceiver(string $login, string $pass): void
    {
        if (!$this->transport->isOpen()) {
            throw new ClosedTransportException();
        }

        $this->logger->info('Binding receiver...');

        $response = $this->bind($login, $pass, Smpp::BIND_RECEIVER);

        $this->logger->info("Binding status  : " . $response->status);

        $this->mode = self::MODE_RECEIVER;
        $this->login = $login;
        $this->pass = $pass;
    }

    /**
     * Binds the transmitter. One object can be bound only as receiver or only as transmitter.
     *
     * @param string $login - ESME system_id
     * @param string $pass - ESME password
     *
     * @return void
     * @throws Exception
     */
    public function bindTransmitter(string $login, string $pass): void
    {
        if (!$this->transport->isOpen()) {
            throw new ClosedTransportException();
        }

        $this->logger->info('Binding transmitter...');

        $response = $this->bind($login, $pass, Smpp::BIND_TRANSMITTER);

        $this->logger->info("Binding status  : " . $response->status);

        $this->mode = self::MODE_TRANSMITTER;
        $this->login = $login;
        $this->pass = $pass;
    }

    /**
     * Bind transceiver, this object bound as receiver and transmitter at same time,
     * only if available in SMPP gateway
     *
     * @param string $login - ESME system_id
     * @param string $pass - ESME password
     *
     * @return void
     * @throws Exception
     */
    public function bindTransceiver(string $login, string $pass): void
    {
        if (!$this->transport->isOpen()) {
            throw new ClosedTransportException();
        }

        $this->logger->info('Binding transciever...');

        $response = $this->bind($login, $pass, Smpp::BIND_TRANSCEIVER);

        $this->logger->info("Binding status  : " . $response->status);

        $this->mode = self::MODE_TRANSCEIVER;
        $this->login = $login;
        $this->pass = $pass;
    }

    /**
     * Closes the session on the SMSC server.
     *
     * @return void
     * @throws Exception
     */
    public function close(): void
    {
        if (!$this->transport->isOpen()) {
            return;
        }

        $this->logger->info('Unbinding...');

        $response = $this->sendCommand(Smpp::UNBIND, "");

        $this->logger->info("Unbind status   : " . $response->status);

        $this->transport->close();
    }

    /**
     * Parse a time string as formatted by SMPP v3.4 section 7.1.
     * Returns an object of either DateTime or DateInterval is returned.
     *
     * @param string $input
     *
     * @return DateTime|DateInterval|null
     *
     * @throws Exception
     */
    public function parseSmppTime(string $input): null|DateTime|DateInterval
    {
        if (
            !preg_match(
                '/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})(\\d{2})(\\d{2})(\\d{1})(\\d{2})([R+-])$/',
                $input,
                $matches
            )
        ) {
            return null;
        }

        /**
         * @var int $y
         * @var int $m
         * @var int $d
         * @var int $h
         * @var int $i
         * @var int $s
         * @var int $n
         * @var string $p
         */
        [$whole, $y, $m, $d, $h, $i, $s, $t, $n, $p] = $matches;

        if ($p == 'R') {
            $spec = "P";
            if ($y) {
                $spec .= $y . 'Y';
            }
            if ($m) {
                $spec .= $m . 'M';
            }
            if ($d) {
                $spec .= $d . 'D';
            }
            if ($h || $i || $s) {
                $spec .= 'T';
            }
            if ($h) {
                $spec .= $h . 'H';
            }
            if ($i) {
                $spec .= $i . 'M';
            }
            if ($s) {
                $spec .= $s . 'S';
            }
            return new DateInterval($spec);
        } else {
            $offsetHours = floor($n / 4);
            $offsetMinutes = ($n % 4) * 15;
            // Not Y3K safe
            $time = sprintf(
                "20%02s-%02s-%02sT%02s:%02s:%02s%s%02s:%02s",
                $y,
                $m,
                $d,
                $h,
                $i,
                $s,
                $p,
                $offsetHours,
                $offsetMinutes
            );
            return new DateTime($time);
        }
    }

    /**
     * Query the SMSC about current state/status of a previous sent SMS.
     * You must specify the SMSC assigned message id and source of the sent SMS.
     * Returns an associative array with elements: message_id, final_date, message_state and error_code.
     *    message_state would be one of the SMPP::STATE_* constants. (SMPP v3.4 section 5.2.28)
     *    error_code depends on the telco network, so could be anything.
     *
     * @param string $messageID
     * @param Address $source
     *
     * @return null|array<string, mixed>
     *
     * @throws Exception
     */
    public function queryStatus(string $messageID, Address $source): null|array
    {
        $pduBody = pack(
            'a' . (strlen($messageID) + 1) . 'cca' . (strlen($source->value) + 1),
            $messageID,
            $source->ton,
            $source->npi,
            $source->value
        );

        $reply = $this->sendCommand(Smpp::QUERY_SM, $pduBody);

        if ($reply->status !== Smpp::ESME_ROK) {
            return null;
        }

        // Parse reply
        $posID = strpos($reply->body, "\0", 0);
        $posDate = strpos($reply->body, "\0", $posID + 1);

        if ($posID === false) {
            // todo: replace exception and add message
            throw new Exception();
        }

        $data = [
            'message_id' => substr($reply->body, 0, $posID),
            'final_date' => substr($reply->body, $posID, (int)$posDate - $posID),
        ];
        $data['final_date'] = $data['final_date'] ? $this->parseSmppTime(trim($data['final_date'])) : null;
        /** @var false|array{message_state: mixed, error_code: mixed} $status */
        $status = unpack("cmessage_state/cerror_code", substr($reply->body, $posDate + 1));

        if (!$status) {
            // todo: replace exception and add message
            throw new Exception();
        }

        return array_merge($data, $status);
    }

    /**
     * Read one SMS from SMSC. Can be executed only after bindReceiver() call.
     * This method blocks. Method returns on socket timeout or enquire_link signal from SMSC.
     *
     * @return DeliveryReceipt|Sms|bool
     * @throws Exception
     */
    public function readSMS(): bool|DeliveryReceipt|Sms
    {
        // Check the queue
        $queueLength = count($this->pduQueue);
        for ($i = 0; $i < $queueLength; $i++) {
            $pdu = $this->pduQueue[$i];
            if ($pdu->id === Smpp::DELIVER_SM) {
                //remove response
                array_splice($this->pduQueue, $i, 1);
                return $this->parseSMS($pdu);
            }
        }
        // Read pdu
        do {
            $pdu = $this->readPDU();
            if ($pdu === false) {
                return false;
            } // TSocket v. 0.6.0+ returns false on timeout
            //check for enquire link command
            if ($pdu->id === Smpp::ENQUIRE_LINK) {
                $response = new Pdu(Smpp::ENQUIRE_LINK_RESP, Smpp::ESME_ROK, $pdu->sequence, "\x00");
                $this->sendPDU($response);
            } else if ($pdu->id !== Smpp::DELIVER_SM) { // if this is not the correct PDU add to queue
                array_push($this->pduQueue, $pdu);
            }
        } while ($pdu->id !== Smpp::DELIVER_SM);

        return $this->parseSMS($pdu);
    }

    /**
     * Send one SMS to SMSC. Can be executed only after bindTransmitter() call.
     * $message is always in octets regardless of the data encoding.
     * For correct handling of Concatenated SMS,
     * message must be encoded with GSM 03.38 (data_coding 0x00) or UCS-2BE (0x08).
     * Concatenated SMS'es uses 16-bit reference numbers, which gives 152 GSM 03.38 chars or 66 UCS-2BE chars per CSMS.
     * If we are using 8-bit ref numbers in the UDH for CSMS it's 153 GSM 03.38 chars
     *
     * @param Address $from
     * @param Address $to
     * @param string $message
     * @param Tag[]|null $tags (optional)
     * @param int $dataCoding (optional)
     * @param int $priority (optional)
     * @param null $scheduleDeliveryTime (optional)
     * @param null $validityPeriod (optional)
     *
     * @return bool|string message id
     *
     * @throws Exception
     */
    public function sendSMS(
        Address $from,
        Address $to,
        string $message,
        array $tags = null,
        int $dataCoding = Smpp::DATA_CODING_DEFAULT,
        int $priority = 0x00,
        $scheduleDeliveryTime = null,
        $validityPeriod = null
    ): bool|string
    {
        $messageLength = strlen($message);

        if ($messageLength > 160 && !in_array($dataCoding, [Smpp::DATA_CODING_UCS2, Smpp::DATA_CODING_DEFAULT])) {
            return false;
        }

        switch ($dataCoding) {
            case Smpp::DATA_CODING_UCS2:
                // in octets, 70 UCS-2 chars
                $singleSmsOctetLimit = 140;
                // There are 133 octets available, but this would split the UCS the middle so use 132 instead
                $csmsSplit = 132;
                $message = mb_convert_encoding($message, 'UCS-2');
                //Update message length with current encoding
                $messageLength = mb_strlen($message);
                break;
            case Smpp::DATA_CODING_DEFAULT:
                // we send data in octets, but GSM 03.38 will be packed in septets (7-bit) by SMSC.
                $singleSmsOctetLimit = 160;
                // send 152/153 chars in each SMS (SMSC will format data)
                $csmsSplit = (self::$csmsMethod == self::CSMS_8BIT_UDH) ? 153 : 152;
                break;
            default:
                $singleSmsOctetLimit = 254; // From SMPP standard
                break;
        }

        // Figure out if we need to do CSMS, since it will affect our PDU
        if ($messageLength > $singleSmsOctetLimit) {
            $doCsms = true;
            if (self::$csmsMethod != self::CSMS_PAYLOAD) {
                $parts = $this->splitMessageString($message, $csmsSplit, $dataCoding);
                $shortMessage = reset($parts);
                $csmsReference = $this->getCsmsReference();
            }
        } else {
            $shortMessage = $message;
            $doCsms = false;
        }

        // Deal with CSMS
        if ($doCsms) {
            if (self::$csmsMethod == self::CSMS_PAYLOAD) {
                $payload = new Tag(Tag::MESSAGE_PAYLOAD, $message, $messageLength);
                // todo: replace array to k=>v storage (Collection??), where key is tag id
                $tags[] = $payload;
                return $this->submitShortMessage(
                    $from,
                    $to,
                    null,
                    $tags,
                    $dataCoding,
                    $priority,
                    $scheduleDeliveryTime,
                    $validityPeriod
                );
            } elseif (self::$csmsMethod == self::CSMS_8BIT_UDH) {
                $sequenceNumber = 1;
                foreach ($parts as $part) {
                    $udh = pack(
                        'cccccc',
                        5,
                        0,
                        3,
                        substr((string)$csmsReference, 1, 1),
                        count($parts),
                        $sequenceNumber
                    );
                    $res = $this->submitShortMessage(
                        $from,
                        $to,
                        $udh . $part,
                        $tags,
                        $dataCoding,
                        $priority,
                        $scheduleDeliveryTime,
                        $validityPeriod,
                        (string)(self::$smsEsmClass | 0x40) //todo: check this
                    );
                    $sequenceNumber++;
                }
                return $res;
            } else {
                $sarMessageRefNumber = new Tag(Tag::SAR_MSG_REF_NUM, $csmsReference, 2, 'n');
                $sarTotalSegments = new Tag(Tag::SAR_TOTAL_SEGMENTS, count($parts), 1, 'c');
                $sequenceNumber = 1;
                foreach ($parts as $part) {
                    $sartags = [
                        $sarMessageRefNumber,
                        $sarTotalSegments,
                        new Tag(Tag::SAR_SEGMENT_SEQNUM, $sequenceNumber, 1, 'c')
                    ];
                    $res = $this->submitShortMessage(
                        $from,
                        $to,
                        (string)$part,
                        (empty($tags) ? $sartags : array_merge($tags, $sartags)),
                        $dataCoding,
                        $priority,
                        $scheduleDeliveryTime,
                        $validityPeriod
                    );
                    $sequenceNumber++;
                }
                return $res;
            }
        }

        return $this->submitShortMessage($from, $to, (string)($shortMessage ?? ''), $tags, $dataCoding, $priority);
    }

    /**
     * Perform the actual submit_sm call to send SMS.
     * Implemented as a protected method to allow automatic sms concatenation.
     * Tags must be an array of already packed and encoded TLV-params.
     *
     * @param Address $source
     * @param Address $destination
     * @param string|null $shortMessage
     * @param Tag[]|null $tags
     * @param integer $dataCoding
     * @param integer $priority
     * @param string|null $scheduleDeliveryTime
     * @param string|null $validityPeriod
     * @param string|null $esmClass
     *
     * @return string message id
     *
     * @throws Exception
     */
    protected function submitShortMessage(
        Address $source,
        Address $destination,
        string $shortMessage = null,
        array $tags = null,
        int $dataCoding = Smpp::DATA_CODING_DEFAULT,
        int $priority = 0x00,
        string $scheduleDeliveryTime = null,
        string $validityPeriod = null,
        string $esmClass = null
    ): string
    {
        if (is_null($esmClass)) {
            $esmClass = self::$smsEsmClass;
        }

        $shortMessageLength = strlen($shortMessage);
        // Construct PDU with mandatory fields
        $pdu = pack(
            'a1cca' . (strlen($source->value) + 1)
            . 'cca' . (strlen($destination->value) + 1)
            . 'ccc' . ($scheduleDeliveryTime ? 'a16x' : 'a1') . ($validityPeriod ? 'a16x' : 'a1')
            . 'ccccca' . ($shortMessageLength + (self::$smsNullTerminateOctetstrings ? 1 : 0)),
            self::$smsServiceType,
            $source->ton,
            $source->npi,
            $source->value,
            $destination->ton,
            $destination->npi,
            $destination->value,
            $esmClass,
            self::$smsProtocolID,
            $priority,
            $scheduleDeliveryTime,
            $validityPeriod,
            self::$smsRegisteredDeliveryFlag,
            self::$smsReplaceIfPresentFlag,
            $dataCoding,
            self::$smsSmDefaultMessageID,
            $shortMessageLength, //sm_length
            $shortMessage //short_message
        );

        // Add any tags
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $pdu .= $tag->getBinary();
            }
        }

        $response = $this->sendCommand(Smpp::SUBMIT_SM, $pdu);
        /** @var array{msgid: string}|false $body */
        $body = unpack("a*msgid", $response->body);
        if (!$body) {
            throw new SmppException('unable to unpack response body:' . $response->body);
        }
        return $body['msgid'];
    }

    /**
     * Get a CSMS reference number for sar_msg_ref_num.
     * Initializes with a random value, and then returns the number in sequence with each call.
     *
     * @return int
     */
    protected function getCsmsReference(): int
    {
        $limit = (self::$csmsMethod == self::CSMS_8BIT_UDH) ? 255 : 65535;
        if (!isset($this->sarMessageReferenceNumber)) {
            $this->sarMessageReferenceNumber = mt_rand(0, $limit);
        }
        $this->sarMessageReferenceNumber++;

        if ($this->sarMessageReferenceNumber > $limit) {
            $this->sarMessageReferenceNumber = 0;
        }
        return $this->sarMessageReferenceNumber;
    }


    /**
     * Split a message into multiple parts, taking the encoding into account.
     * A character represented by an GSM 03.38 escape-sequence shall not be split in the middle.
     * Uses str_split if at all possible, and will examine all split points for escape chars if it's required.
     *
     * @param string $message
     * @param int<1,max> $split
     * @param integer $dataCoding (optional)
     *
     * @return array<int|string>
     */
    protected function splitMessageString(
        string $message,
        int $split,
        int $dataCoding = Smpp::DATA_CODING_DEFAULT
    ): array
    {
        switch ($dataCoding) {
            case Smpp::DATA_CODING_DEFAULT:
                $messageLength = strlen($message);
                // Do we need to do php based split?
                $numParts = floor($messageLength / $split);
                if ($messageLength % $split == 0) {
                    $numParts--;
                }
                $slowSplit = false;

                for ($i = 1; $i <= $numParts; $i++) {
                    if ($message[$i * $split - 1] == "\x1B") {
                        $slowSplit = true;
                        break;
                    }
                }
                if (!$slowSplit) {
                    return str_split($message, $split);
                }

                // Split the message char-by-char
                $parts = [];
                $part = null;
                $n = 0;
                for ($i = 0; $i < $messageLength; $i++) {
                    $c = $message[$i];
                    // reset on $split or if last char is a GSM 03.38 escape char
                    if ($n == $split || ($n == ($split - 1) && $c == "\x1B")) {
                        $parts[] = $part;
                        $n = 0;
                        $part = null;
                    }
                    $part .= $c;
                }
                $parts[] = $part;
                return $parts;
            /**
             * UCS2-BE can just use str_split since we send 132 octets per message,
             * which gives a fine split using UCS2
             */
            case Smpp::DATA_CODING_UCS2:
            default:
                return str_split($message, $split);
        }
    }

    /**
     * Binds the socket and opens the session on SMSC
     *
     * @param string $login - ESME system_id
     * @param string $pass
     * @param int $commandID (todo replace to ENUM in php 8.1)
     *
     * @return Pdu
     *
     * @throws Exception
     */
    protected function bind(string $login, string $pass, int $commandID): Pdu
    {
        // Make PDU body
        $pduBody = pack(
            'a' . (strlen($login) + 1)
            . 'a' . (strlen($pass) + 1)
            . 'a' . (strlen(self::$systemType) + 1)
            . 'CCCa' . (strlen(self::$addressRange) + 1),
            $login,
            $pass,
            self::$systemType,
            self::$interfaceVersion,
            self::$addrTon,
            self::$addrNPI,
            self::$addressRange
        );

        $response = $this->sendCommand($commandID, $pduBody);
        if ($response->status != Smpp::ESME_ROK) {
            throw new SmppException(Smpp::getStatusMessage($response->status), $response->status);
        }

        return $response;
    }

    /**
     * Parse received PDU from SMSC.
     * @param Pdu $pdu - received PDU from SMSC.
     *
     * @return DeliveryReceipt|Sms parsed PDU as array.
     *
     * @throws Exception
     */
    protected function parseSMS(Pdu $pdu): DeliveryReceipt|Sms
    {
        // Check command id
        if ($pdu->id != Smpp::DELIVER_SM) {
            throw new SmppInvalidArgumentException('PDU is not an received SMS');
        }

        // Unpack PDU
        $ar = unpack("C*", $pdu->body);

        if (!$ar) {
            throw new SmppException(''); // todo: update message
        }

        // Read mandatory params
        $serviceType = $this->getString($ar, 6, true);

        //
        $sourceAddrTon = next($ar);
        $sourceAddrNPI = next($ar);
        $sourceAddr = $this->getString($ar, 21);
        $source = new Address($sourceAddr, $sourceAddrTon, $sourceAddrNPI);

        //
        $destinationAddrTon = next($ar);
        $destinationAddrNPI = next($ar);
        $destinationAddr = $this->getString($ar, 21);
        $destination = new Address($destinationAddr, $destinationAddrTon, $destinationAddrNPI);

        $esmClass = next($ar);
        $protocolId = next($ar);
        $priorityFlag = next($ar);
        next($ar); // schedule_delivery_time
        next($ar); // validity_period
        $registeredDelivery = next($ar);
        next($ar); // replace_if_present_flag
        $dataCoding = next($ar);
        next($ar); // sm_default_msg_id
        $sm_length = next($ar);
        $message = $this->getString($ar, $sm_length);

        // Check for optional params, and parse them
        if (current($ar) !== false) {
            $tags = [];
            do {
                $tag = $this->parseTag($ar);
                if ($tag !== false) {
                    $tags[] = $tag;
                }
            } while (current($ar) !== false);
        } else {
            $tags = null;
        }

        if (($esmClass & Smpp::ESM_DELIVER_SMSC_RECEIPT) != 0) {
            $sms = new DeliveryReceipt(
                $pdu->id,
                $pdu->status,
                $pdu->sequence,
                $pdu->body,
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
                $pdu->id,
                $pdu->status,
                $pdu->sequence,
                $pdu->body,
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

        $this->logger->info("Received sms:\n" . print_r($sms, true));

        // Send response of recieving sms
        $response = new Pdu(Smpp::DELIVER_SM_RESP, Smpp::ESME_ROK, $pdu->sequence, "\x00");
        $this->sendPDU($response);
        return $sms;
    }

    /**
     * Send the enquire link command.
     * @return Pdu
     * @throws Exception
     */
    public function enquireLink(): Pdu
    {
        return $this->sendCommand(Smpp::ENQUIRE_LINK, null);
    }

    /**
     * Respond to any enquire link we might have waiting.
     * If will check the queue first and respond to any enquire links we have there.
     * Then it will move on to the transport, and if the first PDU is enquire link respond,
     * otherwise add it to the queue and return.
     *
     */
    public function respondEnquireLink(): void
    {
        // Check the queue first
        $queueLength = count($this->pduQueue);
        for ($i = 0; $i < $queueLength; $i++) {
            $pdu = $this->pduQueue[$i];
            if ($pdu->id == Smpp::ENQUIRE_LINK) {
                //remove response
                array_splice($this->pduQueue, $i, 1);
                $this->sendPDU(new Pdu(Smpp::ENQUIRE_LINK_RESP, Smpp::ESME_ROK, $pdu->sequence, "\x00"));
            }
        }

        // Check the transport for data
        if ($this->transport->hasData()) {
            $pdu = $this->readPDU();
            if ($pdu && $pdu->id == Smpp::ENQUIRE_LINK) {
                $this->sendPDU(new Pdu(Smpp::ENQUIRE_LINK_RESP, Smpp::ESME_ROK, $pdu->sequence, "\x00"));
            } elseif ($pdu) {
                array_push($this->pduQueue, $pdu);
            }
        }
    }

    /**
     * Reconnect to SMSC.
     * This is mostly to deal with the situation were we run out of sequence numbers
     *
     * @throws SmppException|Exception
     */
    protected function reconnect(): void
    {
        $this->close();
        sleep(1);
        $this->transport->open();
        $this->sequenceNumber = 1;

        match ($this->mode) {
            self::MODE_TRANSMITTER => $this->bindTransmitter($this->login, $this->pass),
            self::MODE_RECEIVER => $this->bindReceiver($this->login, $this->pass),
            self::MODE_TRANSCEIVER => $this->bindTransceiver($this->login, $this->pass),
            default => throw new SmppException('Invalid mode: ' . $this->mode)
        };
    }

    /**
     * Sends the PDU command to the SMSC and waits for response.
     * @param int $id - command ID
     * @param ?string $pduBody - PDU body
     * @return Pdu
     *
     * @throws Exception
     */
    protected function sendCommand(int $id, ?string $pduBody): Pdu
    {
        if (!$this->transport->isOpen()) {
            throw new SocketTransportException('Socket is closed');
            //return false;
        }
        $pdu = new Pdu($id, 0, $this->sequenceNumber, $pduBody);
        $this->sendPDU($pdu);
        $response = $this->readPduResponse($this->sequenceNumber, $pdu->id);

        if ($response === false) {
            throw new SmppException('Failed to read reply to command: 0x' . dechex($id));
        }

        if ($response->status != Smpp::ESME_ROK) {
            throw new SmppException(Smpp::getStatusMessage($response->status), $response->status);
        }

        $this->sequenceNumber++;

        // Reached max sequence number, spec does not state what happens now, so we re-connect
        if ($this->sequenceNumber >= 0x7FFFFFFF) {
            $this->reconnect();
        }

        return $response;
    }

    /**
     * Prepares and sends PDU to SMSC.
     * @param Pdu $pdu
     * @throws Exception
     */
    protected function sendPDU(Pdu $pdu): void
    {
        $length = strlen($pdu->body) + 16;
        $header = pack("NNNN", $length, $pdu->id, $pdu->status, $pdu->sequence);

        $this->logger->info("Read PDU         : $length bytes");
        $this->logger->info(' ' . chunk_split(bin2hex($header . $pdu->body), 2, " "));
        $this->logger->info(' command_id      : 0x' . dechex($pdu->id));
        $this->logger->info(' sequence number : ' . $pdu->sequence);

        $this->transport->write($header . $pdu->body, $length);
    }

    /**
     * Waits for SMSC response on specific PDU.
     * If a GENERIC_NACK with a matching sequence number, or null sequence is received instead it's also accepted.
     * Some SMPP servers, ie. logica returns GENERIC_NACK on errors.
     *
     * @param int $sequenceNumber - PDU sequence number
     * @param int $commandID - PDU command ID
     *
     * @return Pdu|false
     * @throws SmppException
     */
    protected function readPduResponse(int $sequenceNumber, int $commandID): Pdu|false
    {
        // Get response cmd id from command ID
        $commandID = $commandID | Smpp::GENERIC_NACK;

        // Check the queue first
        $queueLength = count($this->pduQueue);
        for ($i = 0; $i < $queueLength; $i++) {
            $pdu = $this->pduQueue[$i];
            if (
                ($pdu->sequence == $sequenceNumber && ($pdu->id == $commandID || $pdu->id == Smpp::GENERIC_NACK))
                ||
                ($pdu->sequence == null && $pdu->id == Smpp::GENERIC_NACK)
            ) {
                // remove response pdu from queue
                array_splice($this->pduQueue, $i, 1);
                return $pdu;
            }
        }

        // Read PDUs until the one we are looking for shows up, or a generic nack pdu with matching sequence or null sequence
        do {
            $pdu = $this->readPDU();
            if ($pdu) {
                if (
                    $pdu->sequence == $sequenceNumber
                    && ($pdu->id == $commandID || $pdu->id == Smpp::GENERIC_NACK)
                ) {
                    return $pdu;
                }
                if ($pdu->sequence == null && $pdu->id == Smpp::GENERIC_NACK) {
                    return $pdu;
                }
                array_push($this->pduQueue, $pdu); // unknown PDU push to queue
            }
        } while ($pdu);
        return false;
    }

    /**
     * Reads incoming PDU from SMSC.
     * @return false|Pdu
     */
    protected function readPDU(): Pdu|false
    {
        // Read PDU length
        $bufLength = $this->transport->read(4);
        if (!$bufLength) {
            return false;
        }

        $extract = unpack("Nlength", $bufLength);
        if (!$extract) {
            throw new SmppException('unable to unpack string');
        }
        /**
         * extraction define next variables:
         * @var $length
         */
        extract($extract);

        // Read PDU headers
        $bufHeaders = $this->transport->read(12);
        if (!$bufHeaders) {
            return false;
        }

        $extract = unpack("Ncommand_id/Ncommand_status/Nsequence_number", $bufHeaders);
        if (!$extract) {
            throw new SmppException('unable to unpack string');
        }
        /**
         * @var $command_id
         * @var $command_status
         * @var $sequence_number
         */
        extract($extract);

        if (!isset($command_id, $command_status, $sequence_number, $length)) {
            return false; // todo: maybe replace to exception??
        }

        // Read PDU body
        $bodyLength = $length - 16;
        if ($bodyLength > 0) {
            if (!$body = $this->transport->readAll($bodyLength)) {
                throw new SmppException('Could not read PDU body');
            }
        } else {
            $body = null;
        }

        $this->logger->info("Read PDU         : $length bytes");
        $this->logger->info(' ' . chunk_split(bin2hex($bufLength . $bufHeaders . $body), 2, " "));
        $this->logger->info(" command id      : 0x" . dechex($command_id));
        $this->logger->info(" command status  : 0x" . dechex($command_status) . " " . Smpp::getStatusMessage($command_status));
        $this->logger->info(' sequence number : ' . $sequence_number);

        return new Pdu($command_id, $command_status, $sequence_number, $body);
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
    protected function getString(array &$ar, int $maxLength = 255, bool $firstRead = false): string
    {
        $s = "";
        $i = 0;
        do {
            $c = ($firstRead && $i == 0) ? current($ar) : next($ar);
            if ($c != 0) $s .= chr($c);
            $i++;
        } while ($i < $maxLength && $c != 0);
        return $s;
    }

    /**
     * Read a specific number of octets from the char array.
     * Does not stop at null byte
     *
     * @param array<mixed> $ar - input array
     * @param int $length
     * @return string
     */
    protected function getOctets(array &$ar, int $length): string
    {
        $s = "";
        for ($i = 0; $i < $length; $i++) {
            $c = next($ar);
            if ($c === false) {
                return $s;
            }
            $s .= chr($c);
        }
        return $s;
    }

    /**
     * @param array<mixed> $ar
     * @return false|Tag
     */
    protected function parseTag(array &$ar): false|Tag
    {
        $unpackedData = unpack(
            'nid/nlength',
            pack("C2C2", next($ar), next($ar), next($ar), next($ar))
        );

        if (!$unpackedData) {
            throw new SmppInvalidArgumentException('Could not read tag data');
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
        $tag = new Tag($id, $value, $length);

        $this->logger->info("Parsed tag:");
        $this->logger->info(" id     :0x" . dechex($tag->id));
        $this->logger->info(" length :" . $tag->length);
        $this->logger->info(" value  :" . chunk_split(bin2hex((string)$tag->value), 2, " "));

        return $tag;
    }
}
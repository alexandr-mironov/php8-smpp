<?php

declare(strict_types=1);

namespace smpp;

/**
 * Class Sms
 * @package smpp
 */
class Sms extends Pdu
{
    /**
     * Construct a new SMS
     *
     * @param integer $id
     * @param integer $status
     * @param integer $sequence
     * @param string $body
     * @param string $serviceType
     * @param Address $source
     * @param Address $destination
     * @param integer $esmClass
     * @param integer $protocolId
     * @param integer $priorityFlag
     * @param integer $registeredDelivery
     * @param integer $dataCoding
     * @param string $message
     * @param Tag[] $tags (optional)
     * @param string|null $scheduleDeliveryTime (optional)
     * @param string|null $validityPeriod (optional)
     * @param int|null $smDefaultMsgId (optional)
     * @param int|null $replaceIfPresentFlag (optional)
     */
    public function __construct(
        int                $id,
        int                $status,
        int                $sequence,
        string             $body,
        public string      $serviceType,
        public Address     $source,
        public Address     $destination,
        public int         $esmClass,
        public int         $protocolId,
        public int         $priorityFlag,
        public int         $registeredDelivery,
        public int         $dataCoding,
        public string      $message,
        public array       $tags,
        // Unused in deliver_sm
        public string|null $scheduleDeliveryTime = null,
        public string|null $validityPeriod = null,
        public int|null    $smDefaultMsgId = null,
        public int|null    $replaceIfPresentFlag = null
    )
    {
        parent::__construct($id, $status, $sequence, $body);
    }
}
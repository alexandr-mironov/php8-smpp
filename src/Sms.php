<?php


namespace smpp;

/**
 * Primitive type to represent SMSes
 * @author hd@onlinecity.dk
 */
class Sms extends Pdu
{
    public  string  $serviceType;
    public  Address $source;
    public  Address $destination;
    public  int     $esmClass;
    public  int     $protocolId;
    public  int     $priorityFlag;
    public  int     $registeredDelivery;
    public  int     $dataCoding;
    public  string  $message;
    public  array   $tags;

    // Unused in deliver_sm
    public string   $scheduleDeliveryTime;
    public string   $validityPeriod;
    public int      $smDefaultMsgId;
    public int      $replaceIfPresentFlag;

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
     * @param array $tags (optional)
     * @param string $scheduleDeliveryTime (optional)
     * @param string $validityPeriod (optional)
     * @param integer $smDefaultMsgId (optional)
     * @param integer $replaceIfPresentFlag (optional)
     */
    public function __construct(
        int $id,
        int $status,
        int $sequence,
        string $body,
        public string $serviceType,
        public Address $source,
        public Address $destination,
        public int $esmClass,
        public int $protocolId,
        public int $priorityFlag,
        public int $registeredDelivery,
        public int $dataCoding,
        public string $message,
        public array $tags,
        public string|null $scheduleDeliveryTime = null,
        public string|null $validityPeriod = null,
        public int|null $smDefaultMsgId = null,
        public int|null $replaceIfPresentFlag = null
    )
    {
        parent::__construct($id, $status, $sequence, $body);
//        $this->service_type = $service_type;
//        $this->source = $source;
//        $this->destination = $destination;
//        $this->esmClass = $esmClass;
//        $this->protocolId = $protocolId;
//        $this->priorityFlag = $priorityFlag;
//        $this->registeredDelivery = $registeredDelivery;
//        $this->dataCoding = $dataCoding;
//        $this->message = $message;
//        $this->tags = $tags;
//        $this->scheduleDeliveryTime = $scheduleDeliveryTime;
//        $this->validityPeriod = $validityPeriod;
//        $this->smDefaultMsgId = $smDefaultMsgId;
//        $this->replaceIfPresentFlag = $replaceIfPresentFlag;
    }
}
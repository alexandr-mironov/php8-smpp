<?php

declare(strict_types=1);


namespace Smpp;


use Smpp\Contracts\ValidatorInterface;
use Smpp\Exceptions\SmppException;
use Smpp\Validators\AddressRangeValidator;
use Smpp\Validators\NumberingPlanIndicatorValidator;
use Smpp\Validators\TypeOfNumberValidator;

class SmppConfig
{
    /**
     * ESME system type identifier for SMPP bind.
     * For example:
     *  - VMS - voice mail
     *  - SMSGW - SMS gateway
     *  - WAP - some WAP service
     *  - '' (empty string) -  not defined
     * Used by SMSC to classify client connections.
     *
     * @var string $systemType
     */
    private string $systemType = "WWW";

    /**
     * @var int
     */
    private int $interfaceVersion = 0x34;

    /**
     * @var int address type of number value
     */
    private int $addressNumberType = Smpp::TON_UNKNOWN;

    /**
     * NPI (Numbering Plan Indicator) - numbering scheme for the address (e.g. phone number).
     * Common values:
     * 1 = ISDN/E.164 (standard phone numbers),
     * 8 = National,
     * 0 = Unknown.
     * Used with TON (Type of Number) to properly format addresses in SMPP.
     *
     * @var int $addressNumberingPlanIndicator
     */
    private int $addressNumberingPlanIndicator = Smpp::NPI_UNKNOWN;

    /**
     * SMPP address range (addrRange) filter for message routing/subscription.
     *
     * - Defines a pattern for matching destination/source addresses (MSISDNs, short codes, etc.)
     * - Used in operations like `bind_receiver`, `submit_sm`, and `deliver_sm`
     * - Supports wildcards:
     *   - `*` matches any sequence of digits (e.g., "7916*" → all numbers starting with 7916)
     *   - `?` matches exactly one digit (e.g., "7912?45" → 7912345, 7912545, etc.)
     * - Null/empty value means "no filter" (match all addresses)
     *
     * @var string $addressRange
     * @example "+7916*", "12345", "???99"
     * @see SMPP 3.4 Specification, Section 5.2.5
     */
    private string $addressRange = "";

    /** @var string */
    private string $smsServiceType = "";
    /** @var int */
    private int $smsEsmClass = 0x00;
    /** @var int */
    private int $smsProtocolID = 0x00;
    /** @var int */
    private int $smsPriorityFlag = 0x00;
    /** @var int */
    private int $smsRegisteredDeliveryFlag = 0x00;
    /** @var int */
    private int $smsReplaceIfPresentFlag = 0x00;
    /** @var int */
    private int $smsSmDefaultMessageID = 0x00;

    public function __construct()
    {
    }

    /**
     * @return string
     */
    public function getSystemType(): string
    {
        return $this->systemType;
    }

    /**
     * @param string $systemType
     * @return SmppConfig
     */
    public function setSystemType(string $systemType): SmppConfig
    {
        $this->systemType = $systemType;
        return $this;
    }

    /**
     * @return int
     */
    public function getInterfaceVersion(): int
    {
        return $this->interfaceVersion;
    }

    /**
     * @param int $interfaceVersion
     * @return SmppConfig
     */
    public function setInterfaceVersion(int $interfaceVersion): SmppConfig
    {
        $this->interfaceVersion = $interfaceVersion;
        return $this;
    }

    /**
     * @return int
     */
    public function getAddressNumberType(): int
    {
        return $this->addressNumberType;
    }

    /**
     * @param int $addressNumberType
     * @return SmppConfig
     * @throws SmppException
     */
    public function setAddressNumberType(int $addressNumberType): SmppConfig
    {
        $this->validate($addressNumberType, new TypeOfNumberValidator());

        $this->addressNumberType = $addressNumberType;
        return $this;
    }

    /**
     * @param mixed $value
     * @param ValidatorInterface ...$validators
     * @throws SmppException
     */
    private function validate(mixed $value, ValidatorInterface ...$validators)
    {
        foreach ($validators as $validator) {
            $validationResult = $validator->isValid($value);

            if ($validationResult !== null) {
                throw $validationResult;
            }
        }
    }

    /**
     * @return int
     */
    public function getAddressNumberingPlanIndicator(): int
    {
        return $this->addressNumberingPlanIndicator;
    }

    /**
     * @param int $numberingPlanIndicator
     * @return SmppConfig
     * @throws SmppException
     */
    public function setAddressNumberingPlanIndicator(int $numberingPlanIndicator): SmppConfig
    {
        $this->validate($numberingPlanIndicator, new NumberingPlanIndicatorValidator());

        $this->addressNumberingPlanIndicator = $numberingPlanIndicator;
        return $this;
    }

    /**
     * @return string
     */
    public function getAddressRange(): string
    {
        return $this->addressRange;
    }

    /**
     * @param string $addressRange
     * @return SmppConfig
     * @throws SmppException
     */
    public function setAddressRange(string $addressRange): SmppConfig
    {
        $this->validate($addressRange, new AddressRangeValidator());

        $this->addressRange = $addressRange;
        return $this;
    }

    /**
     * @return string
     */
    public function getSmsServiceType(): string
    {
        return $this->smsServiceType;
    }

    /**
     * @param string $smsServiceType
     * @return SmppConfig
     */
    public function setSmsServiceType(string $smsServiceType): SmppConfig
    {
        $this->smsServiceType = $smsServiceType;
        return $this;
    }

    /**
     * @return int
     */
    public function getSmsEsmClass(): int
    {
        return $this->smsEsmClass;
    }

    /**
     * @param int $smsEsmClass
     * @return SmppConfig
     */
    public function setSmsEsmClass(int $smsEsmClass): SmppConfig
    {
        $this->smsEsmClass = $smsEsmClass;
        return $this;
    }

    /**
     * @return int
     */
    public function getSmsProtocolID(): int
    {
        return $this->smsProtocolID;
    }

    // ESME transmitter parameters

    /**
     * @param int $smsProtocolID
     * @return SmppConfig
     */
    public function setSmsProtocolID(int $smsProtocolID): SmppConfig
    {
        $this->smsProtocolID = $smsProtocolID;
        return $this;
    }

    /**
     * @return int
     */
    public function getSmsPriorityFlag(): int
    {
        return $this->smsPriorityFlag;
    }

    /**
     * @param int $smsPriorityFlag
     * @return SmppConfig
     */
    public function setSmsPriorityFlag(int $smsPriorityFlag): SmppConfig
    {
        $this->smsPriorityFlag = $smsPriorityFlag;
        return $this;
    }

    /**
     * @return int
     */
    public function getSmsRegisteredDeliveryFlag(): int
    {
        return $this->smsRegisteredDeliveryFlag;
    }

    /**
     * @param int $smsRegisteredDeliveryFlag
     * @return SmppConfig
     */
    public function setSmsRegisteredDeliveryFlag(int $smsRegisteredDeliveryFlag): SmppConfig
    {
        $this->smsRegisteredDeliveryFlag = $smsRegisteredDeliveryFlag;
        return $this;
    }

    /**
     * @return int
     */
    public function getSmsReplaceIfPresentFlag(): int
    {
        return $this->smsReplaceIfPresentFlag;
    }

    /**
     * @param int $smsReplaceIfPresentFlag
     * @return SmppConfig
     */
    public function setSmsReplaceIfPresentFlag(int $smsReplaceIfPresentFlag): SmppConfig
    {
        $this->smsReplaceIfPresentFlag = $smsReplaceIfPresentFlag;
        return $this;
    }

    /**
     * @return int
     */
    public function getSmsSmDefaultMessageID(): int
    {
        return $this->smsSmDefaultMessageID;
    }

    /**
     * @param int $smsSmDefaultMessageID
     * @return SmppConfig
     */
    public function setSmsSmDefaultMessageID(int $smsSmDefaultMessageID): SmppConfig
    {
        $this->smsSmDefaultMessageID = $smsSmDefaultMessageID;
        return $this;
    }
}
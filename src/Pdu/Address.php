<?php

declare(strict_types=1);


namespace Smpp\Pdu;

use Smpp\Exceptions\SmppInvalidArgumentException;
use Smpp\Smpp;

/**
 * Primitive class for encapsulating smpp addresses
 * @author hd@onlinecity.dk
 */
class Address
{
    /**
     * Construct a new object of class Address
     * @param string $value
     * @param int $numberType - Type Of Number
     * @param int $numberingPlanIndicator - Numbering Plan Indicator
     * @throws SmppInvalidArgumentException
     */
    public function __construct(
        private string $value,
        private int $numberType = Smpp::TON_UNKNOWN,
        private int $numberingPlanIndicator = Smpp::NPI_UNKNOWN
    )
    {
        // Address-Value field may contain 10 octets (12-length-type), see 3GPP TS 23.040 v 9.3.0 - section 9.1.2.5 page 46.
        if ($numberType === Smpp::TON_ALPHANUMERIC && strlen($value) > 11) {
            throw new SmppInvalidArgumentException('Alphanumeric address may only contain 11 chars');
        }
        if ($numberType === Smpp::TON_INTERNATIONAL && $numberingPlanIndicator == Smpp::NPI_E164 && strlen($value) > 15) {
            throw new SmppInvalidArgumentException('E164 address may only contain 15 digits');
        }
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @return int
     */
    public function getNumberType(): int
    {
        return $this->numberType;
    }

    /**
     * @return int
     */
    public function getNumberingPlanIndicator(): int
    {
        return $this->numberingPlanIndicator;
    }
}
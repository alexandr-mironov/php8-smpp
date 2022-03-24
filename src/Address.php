<?php

declare(strict_types=1);


namespace smpp;

use smpp\exceptions\SmppInvalidArgumentException;

/**
 * Primitive class for encapsulating smpp addresses
 * @author hd@onlinecity.dk
 */
class Address
{
    /**
     * Construct a new object of class Address
     * @param string $value
     * @param int $ton - Type Of Number
     * @param int $npi - Numbering Plan Indicator
     */
    public function __construct(
        public string $value,
        public int    $ton = Smpp::TON_UNKNOWN,
        public int    $npi = Smpp::NPI_UNKNOWN
    )
    {
        // Address-Value field may contain 10 octets (12-length-type), see 3GPP TS 23.040 v 9.3.0 - section 9.1.2.5 page 46.
        if ($ton === Smpp::TON_ALPHANUMERIC && strlen($value) > 11) {
            throw new SmppInvalidArgumentException('Alphanumeric address may only contain 11 chars');
        }
        if ($ton === Smpp::TON_INTERNATIONAL && $npi == Smpp::NPI_E164 && strlen($value) > 15) {
            throw new SmppInvalidArgumentException('E164 address may only contain 15 digits');
        }
    }
}
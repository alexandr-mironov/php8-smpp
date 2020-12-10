<?php


namespace smpp;

/**
 * Primitive class for encapsulating smpp addresses
 * @author hd@onlinecity.dk
 */
class Address
{
    /**
     * Construct a new object of class Address
     *
     * @param string $value
     * @param integer $ton
     * @param integer $npi
     * @throws \InvalidArgumentException
     */
    public function __construct(
        /** @var string $value */
        public  string  $value,
        /** @var int $ton - Type Of Number */
        public  int     $ton = Smpp::TON_UNKNOWN,
        /** @var int $npi - Numbering Plan Indicator */
        public  int     $npi = Smpp::NPI_UNKNOWN
    )
    {
        // Address-Value field may contain 10 octets (12-length-type), see 3GPP TS 23.040 v 9.3.0 - section 9.1.2.5 page 46.
        if ($ton == SMPP::TON_ALPHANUMERIC && strlen($value) > 11) {
            throw new \InvalidArgumentException('Alphanumeric address may only contain 11 chars');
        }
        if ($ton == Smpp::TON_INTERNATIONAL && $npi == Smpp::NPI_E164 && strlen($value) > 15) {
            throw new \InvalidArgumentException('E164 address may only contain 15 digits');
        }
    }
}
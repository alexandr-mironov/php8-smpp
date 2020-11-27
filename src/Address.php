<?php


namespace smpp;

/**
 * Primitive class for encapsulating smpp addresses
 * @author hd@onlinecity.dk
 */
class Address
{
    /** @var int $ton - Type Of Number */
    public  int     $ton;
    /** @var int $npi - Numbering Plan Indicator */
    public  int     $npi;
    /** @var string $value */
    public  string  $value;

    /**
     * Construct a new object of class Address
     *
     * @param string $value
     * @param integer $ton
     * @param integer $npi
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public string $value,
        public int $ton = Smpp::TON_UNKNOWN,
        public int $npi = Smpp::NPI_UNKNOWN
    )
    {
        // Address-Value field may contain 10 octets (12-length-type), see 3GPP TS 23.040 v 9.3.0 - section 9.1.2.5 page 46.
        if ($ton == SMPP::TON_ALPHANUMERIC && strlen($value) > 11) {
            throw new \InvalidArgumentException('Alphanumeric address may only contain 11 chars');
        }
        if ($ton == Smpp::TON_INTERNATIONAL && $npi == Smpp::NPI_E164 && strlen($value) > 15) {
            throw new \InvalidArgumentException('E164 address may only contain 15 digits');
        }

//        $this->value = (string) $value;
//        $this->ton = $ton;
//        $this->npi = $npi;
    }
}
<?php

declare(strict_types=1);


namespace Smpp\Validators;

use Smpp\Contracts\ValidatorInterface;
use Smpp\Exceptions\SmppException;
use Smpp\Exceptions\SmppInvalidArgumentException;

class AddressRangeValidator implements ValidatorInterface
{
    public function __construct(
        private int $addressRangeMaxLength = 20
    )
    {
    }

    /**
     * @param string $value
     * @return SmppException|null
     */
    public function isValid($value): ?SmppException
    {
        //Maximum length (depending on operator)
        if ($this->maxLengthValidation($value)) {
            return new SmppInvalidArgumentException("addrRange too long (max $this->addressRangeMaxLength chars)");
        }

        // Check for valid characters: numbers, +, *, ?, #
        if (!preg_match('/^[\d+*?#]+$/', $value)) {
            return new SmppInvalidArgumentException("addrRange contains invalid characters. Only digits, +, *, ?, # allowed");
        }

        // Check wildcard * (if not allowed in the middle)
        if (str_contains($value, '*') && !str_ends_with($value, '*')) {
            return new SmppInvalidArgumentException("Wildcard * is only allowed at the end of addrRange");
        }

        // Wildcard check ? (must replace exactly 1 character)
        if (str_contains($value, '?')) {
            $withoutWildcards = str_replace('?', '', $value);
            if (strlen($withoutWildcards) + substr_count($value, '?') !== strlen($value)) {
                return new SmppInvalidArgumentException("Wildcard ? must replace exactly one character");
            }
        }

        return null;
    }


    /**
     * @param string $addressRange The address range string to validate.
     * @return bool True if the length exceeds the maximum; otherwise, false.
     */

    private function maxLengthValidation(string $addressRange): bool
    {
        // Validates whether the provided address range exceeds the maximum allowed length.
        return strlen($addressRange) > $this->addressRangeMaxLength;
    }
}
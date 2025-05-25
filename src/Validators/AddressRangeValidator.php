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
    {}

    /**
     * @param string $value
     * @return SmppException|null
     */
    public function isValid($value): ?SmppException
    {
        // Максимальная длина (зависит от оператора)
        if ($this->maxLengthValidation($value)) {
            return new SmppInvalidArgumentException("addrRange too long (max $this->addressRangeMaxLength chars)");
        }

        // Проверка допустимых символов: цифры, +, *, ?, #
        if (!preg_match('/^[\d+*?#]+$/', $value)) {
            return new SmppInvalidArgumentException("addrRange contains invalid characters. Only digits, +, *, ?, # allowed");
        }

        // Проверка wildcard * (если нельзя в середине)
        if (str_contains($value, '*') && !str_ends_with($value, '*')) {
            return new SmppInvalidArgumentException("Wildcard * is only allowed at the end of addrRange");
        }

        // Проверка wildcard ? (должен заменять ровно 1 символ)
        if (str_contains($value, '?')) {
            $withoutWildcards = str_replace('?', '', $value);
            if (strlen($withoutWildcards) + substr_count($value, '?') !== strlen($value)) {
                return new SmppInvalidArgumentException("Wildcard ? must replace exactly one character");
            }
        }

        return null;
    }

    /**
     * @param string $addressRange
     * @return bool
     */
    private function maxLengthValidation(string $addressRange): bool
    {
        return strlen($addressRange) > $this->addressRangeMaxLength;
    }
}
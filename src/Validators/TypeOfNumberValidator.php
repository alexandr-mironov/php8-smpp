<?php

declare(strict_types=1);


namespace Smpp\Validators;


use Smpp\Contracts\ValidatorInterface;
use Smpp\Exceptions\SmppException;
use Smpp\Exceptions\SmppInvalidArgumentException;
use Smpp\Smpp;

class TypeOfNumberValidator implements ValidatorInterface
{

    /**
     * @param int $value
     * @return SmppException|null
     */
    public function isValid(mixed $value): ?SmppException
    {
        if (
            in_array(
                $value,
                [
                    Smpp::TON_UNKNOWN,
                    Smpp::TON_INTERNATIONAL,
                    Smpp::TON_NATIONAL,
                    Smpp::TON_NETWORKSPECIFIC,
                    Smpp::TON_SUBSCRIBERNUMBER,
                    Smpp::TON_ALPHANUMERIC,
                    Smpp::TON_ABBREVIATED
                ],
                true
            ) === false
        ) {
            return new SmppInvalidArgumentException('Invalid address type of number: ' . $value);
        }

        return null;
    }
}
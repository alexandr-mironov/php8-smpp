<?php

declare(strict_types=1);


namespace Smpp\Validators;


use Smpp\Contracts\ValidatorInterface;
use Smpp\Exceptions\SmppException;
use Smpp\Exceptions\SmppInvalidArgumentException;
use Smpp\Smpp;

class NumberingPlanIndicatorValidator implements ValidatorInterface
{
    /**
     * @param int $value
     * @return SmppException|null
     */
    public function isValid($value): ?SmppException
    {
        if (
            in_array(
                $value,
                [
                    Smpp::NPI_UNKNOWN,
                    Smpp::NPI_E164,
                    Smpp::NPI_DATA,
                    Smpp::NPI_TELEX,
                    Smpp::NPI_E212,
                    Smpp::NPI_NATIONAL,
                    Smpp::NPI_PRIVATE,
                    Smpp::NPI_ERMES,
                    Smpp::NPI_INTERNET,
                    Smpp::NPI_WAPCLIENT
                ],
                true
            ) === false
        ) {
            return new SmppInvalidArgumentException('Invalid numbering plan indicator value provided: ' . $value);
        }

        return null;
    }
}
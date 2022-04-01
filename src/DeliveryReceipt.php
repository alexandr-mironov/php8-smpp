<?php

declare(strict_types=1);

namespace smpp;

use InvalidArgumentException;

/**
 * An extension of a SMS, with data embedded into the message part of the SMS.
 * @author hd@onlinecity.dk
 */
class DeliveryReceipt extends Sms
{
    public int $id;
    public int $sub;
    public int $dlvrd;
    public int $submitDate;
    public int $doneDate;
    public string $stat;
    public int $err;
    public string $text;

    /**
     * Parse a delivery receipt formatted as specified in SMPP v3.4 - Appendix B
     * It accepts all chars except space as the message id
     *
     * @throws InvalidArgumentException
     */
    public function parseDeliveryReceipt(): void
    {
        $numMatches = preg_match(
            '/^id:([^ ]+) sub:(\d{1,3}) dlvrd:(\d{3}) submit date:(\d{10,12}) done date:(\d{10,12}) stat:([A-Z ]{7}) err:(\d{2,3}) text:(.*)$/si',
            $this->message,
            $matches
        );
        if ($numMatches === 0) {
            throw new InvalidArgumentException(
                'Could not parse delivery receipt: '
                . $this->message
                . "\n"
                . bin2hex($this->body)
            );
        }
        [
            $matched,
            $this->id,
            $this->sub,
            $this->dlvrd,
            $this->submitDate,
            $this->doneDate,
            $this->stat,
            $this->err,
            $this->text
        ] = $matches;

        // Convert dates
        $dp = str_split((string)$this->submitDate, 2);
        $this->submitDate = gmmktime(
            (int)$dp[3],
            (int)$dp[4],
            (int)$dp[5] ?? 0,
            (int)$dp[1],
            (int)$dp[2],
            (int)$dp[0]
        );
        $dp = str_split($this->doneDate, 2);
        $this->doneDate = gmmktime(
            (int)$dp[3],
            (int)$dp[4],
            (int)$dp[5] ?? 0,
            (int)$dp[1],
            (int)$dp[2],
            (int)$dp[0]
        );
    }
}
# php8-smpp
SMPP Client (v 3.4) on PHP8

[![phpstan](https://badgen.net/github/checks/alexandr-mironov/php8-smpp/main/PHPStan)]()
[![stars](https://badgen.net/github/stars/alexandr-mironov/php8-smpp/)]()
[![forks](https://badgen.net/github/forks/alexandr-mironov/php8-smpp/)]()
[![open-issues](https://badgen.net/github/open-issues/alexandr-mironov/php8-smpp/)]()

# ATTENTION!
## In development! Not production ready!

SMPP documentation [here](https://smpp.org/SMPP_v3_4_Issue1_2.pdf)

____
## readme below from https://github.com/alexandr-mironov/php-smpp 

PHP SMPP (v3.4) client
====

Install:

    composer require alexandr-mironov/php8-smpp

Example of wrapper (php>=7.0) for this Client.
In this case we got ALPHANUMERIC sender value 'github_example':

```php
<?php

declare(strict_types=1);

namespace app\components\Sms;

use Exception;
use Smpp\{Pdu\Address, Client as SmppClient, Smpp, Transport\SocketTransport};

class SmsBuilder
{
    /** @var string 11 chars limit */
    public const DEFAULT_SENDER = 'example';

    protected SocketTransport $transport;

    protected SmppClient $smppClient;

    protected bool $debug = false;

    protected Address $from;

    protected Address $to;

    protected string $login;

    protected string $password;

    /**
     * SmsBuilder constructor.
     *
     * @param string $address SMSC IP
     * @param int $port SMSC port
     * @param string $login
     * @param string $password
     * @param int $timeout timeout of reading PDU in milliseconds
     * @param bool $debug - debug flag when true output additional info
     */
    public function __construct(
        string $address,
        int $port,
        string $login,
        string $password,
        int $timeout = 10000,
        bool $debug = false
    ) {
        $this->transport = new SocketTransport([$address], $port);
        
        $this->transport->setRecvTimeout($timeout);
        $this->smppClient = new SmppClient($this->transport);

        $this->login = $login;
        $this->password = $password;

        $this->from = new Address(self::DEFAULT_SENDER, SMPP::TON_ALPHANUMERIC);
    }

    /**
     * @param string $sender
     * @param int $ton
     *
     * @return $this
     * @throws Exception
     */
    public function setSender(string $sender, int $ton): SmsBuilder
    {
        return $this->setAddress($sender, 'from', $ton);
    }

    /**
     * @param string $address
     * @param string $type
     * @param int $ton
     * @param int $npi
     *
     * @return $this
     * @throws Exception
     */
    protected function setAddress(
        string $address,
        string $type,
        int $ton = SMPP::TON_UNKNOWN,
        int $npi = SMPP::NPI_UNKNOWN
    ): SmsBuilder {
        // some example of data preparation
        if ($ton === SMPP::TON_INTERNATIONAL) {
            $npi = SMPP::NPI_E164;
        }
        $this->$type = new Address($address, $ton, $npi);

        return $this;
    }

    /**
     * @param string $address
     * @param int $ton
     *
     * @return $this
     * @throws Exception
     */
    public function setRecipient(string $address, int $ton): SmsBuilder
    {
        return $this->setAddress($address, 'to', $ton);
    }

    /**
     * @param string $message
     *
     * @throws Exception
     */
    public function sendMessage(string $message): void
    {
        $this->transport->open();
        $this->smppClient->bindTransceiver($this->login, $this->password);
        // strongly recommend use SMPP::DATA_CODING_UCS2 as default encoding in project to prevent problems with non latin symbols
        $this->smppClient->sendSMS($this->from, $this->to, $message, null, SMPP::DATA_CODING_UCS2);
        $this->smppClient->close();
    }
}
```

This wrapper implement some kind of Builder pattern, usage example:

```php
<?php
// replace address, port, login and password to your values
(new your_namespace\SmsBuilder('192.168.1.1', '2776', 'your_login', 'your_password', 10000))
    ->setRecipient('79000000000', \Smpp\SMPP::TON_INTERNATIONAL) //msisdn of recipient
    ->sendMessage('Тестовое сообщение на русском and @noth3r$Ymb0ls');
```


[Legacy (original) README](/docs/original_README.md)
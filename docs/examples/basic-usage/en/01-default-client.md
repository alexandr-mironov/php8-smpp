```php
<?php 

declare(strict_types=1);

use Smpp\ClientBuilder;
use Smpp\Pdu\Address;
use Smpp\Smpp;

// Most basic use case
$client = ClientBuilder::createForSockets(['smpp.host.domain:2775'])
            ->setCredentials(getenv('SYSTEM_ID'), getenv('PASSWORD'))
            ->buildClient();

$client->bindTransceiver();

$client->sendSMS(
        from: new Address("php8-smpp", Smpp::TON_ALPHANUMERIC),
        to: new Address("79000000000"), 
        message: "Some kind of message"
    );

$client->close();

```
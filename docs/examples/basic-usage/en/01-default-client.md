```php
<?php 

declare(strict_types=1);

namespace App;

use Smpp\ClientBuilder;

$client = ClientBuilder::createForSockets(['smpp.host.domain:2775'])
            ->setCredentials(getenv('SYSTEM_ID'), getenv('PASSWORD'))
            ->buildClient();

$client->bindTransceiver();

```
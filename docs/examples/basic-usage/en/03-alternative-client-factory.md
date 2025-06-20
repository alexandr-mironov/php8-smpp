# Building an SMPP Client Without ClientBuilder

## When to Use This Approach
Consider manual client construction when you need:
- Custom transport configuration beyond builder defaults
- Specialized dependency injection
- Framework integration (Symfony/Laravel services)

## Core Requirements
| Component       | Purpose                          | Example Source         |
|-----------------|----------------------------------|------------------------|
| Transport       | Network communication layer     | `SocketTransport`      |
| Credentials     | SMPP authentication             | Environment variables  |
| Logger          | Connection/message logging      | PSR-3 implementation   |
| Config          | Protocol behavior tuning        | `SmppConfig`           |

### Example
#### (copy-paste not friendly, just as theoretical example)

Example below describe how to build client without ClientBuilder 

```php
<?php

declare(strict_types=1);


use Smpp\Client;
use Smpp\Configs\SmppConfig;
use Smpp\Configs\SocketTransportConfig;
use Smpp\Transport\SocketTransport;
use Smpp\Utils\Network\DSNParser;


// replace to your own way to get instance of logger which implements LoggerInterface
$logger = LoggerFactory::getStdoutLogger('smpp');

// used in transport
$host = getenv('SMPP_HOST');
$port = getenv('SMPP_PORT');

// used in protocol (client)
$systemId = getenv('SMPP_SYSTEM_ID');
$password = getenv('SMPP_PASSWORD');

// transport part
$dsn = $host . ":" . $port;

$entries = DSNParser::parseDSNEntries($dsn);

$socketTransportConfig = new SocketTransportConfig();

$transport = new SocketTransport($entries, $socketTransportConfig);

$transport->logger = $logger;

// client (protocol) part

$config = new SmppConfig();

$client = new Client(
            $transport,
            $systemId,
            $password,
        );

$client->logger = $logger;
$client->config = $config;

```

## Complete Implementation Example

```php
<?php
declare(strict_types=1);

use Smpp\Client;
use Smpp\Utils\Network\DSNParser;
use Smpp\Configs\{SmppConfig, SocketTransportConfig};
use Smpp\Transport\SocketTransport;
use Psr\Log\LoggerInterface;

final class SmppClientFactory 
{
    public static function create(
        LoggerInterface $logger,
        string $dsn,
        string $systemId,
        string $password,
        ?SmppConfig $clientConfig = null,
        ?SocketTransportConfig $transportConfig = null
    ): Client {
        // Transport setup
        $transport = new SocketTransport(
            DSNParser::parseDSNEntries($dsn),
            $transportConfig ?? new SocketTransportConfig()
        );
        $transport->logger = $logger;

        // Client setup
        $client = new Client($transport, $systemId, $password);
        $client->logger = $logger;
        if ($clientConfig) {
            $client->config = $clientConfig;
        }
        
        return $client;
    }
}
```

### Service configuration example
```yaml
# Symfony services.yaml
services:
  Smpp\Client:
    factory: ['App\Smpp\ClientFactory', 'create']
    arguments:
      $logger: '@monolog.logger.smpp'
      $dsn: '%env(SMPP_DSN)%'
      $systemId: '%env(SMPP_SYSTEM_ID)%'
      $password: '%env(SMPP_PASSWORD)%'
```


### Builder vs Manual Construction

| Scenario                | Builder          | Manual          |
|-------------------------|------------------|-----------------|
| Quick setup             | ✅ Ideal         | ⚠️ Overkill     |
| Custom configurations   | Limited          | ✅ Full control |
| Framework integration   | Possible         | ✅ Cleaner      |
| Learning curve          | Low              | Moderate        |
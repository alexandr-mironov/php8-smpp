## Configuring SMPP Client with Retryable Non-Blocking Reads

This example demonstrates how to configure an SMPP client with automatic retry logic for non-blocking socket operations.

### Code Example

```php
<?php

declare(strict_types=1);

use Smpp\ClientBuilder;
use Smpp\Configs\SocketTransportConfig;
use Smpp\Transport\NonBlockingReadStrategy;
use Smpp\Transport\RetryableReadDecorator;

// 1. Create transport configuration
$transportConfig = new SocketTransportConfig();

// 2. Configure reading strategy:
//    - Non-blocking primary read
//    - 5 retry attempts
//    - 200ms delay between retries
$transportConfig->setReadStrategy(
    new RetryableReadDecorator(
        new NonBlockingReadStrategy(),
        5,      // Max retry attempts
        200     // Delay between retries (milliseconds)
    )
);

// 3. Build client with configured transport
$client = ClientBuilder::createForSockets(
        ['smpp.host.domain:2275'], 
        $transportConfig
    )
    ->setCredentials('systemId', 'password')
    ->buildClient();
```

### Key Features

1. **Non-Blocking**:
    - Immediate response if data is available
    - No thread/process blocking

2. **Automatic Retry Logic**:
    - Configurable number of retries (5 in example)
    - Adjustable delay between attempts (200ms in example)

3. **Seamless Integration**:
    - Works with existing `TransportInterface`
    - No changes required in client business logic

### When to Use This Configuration

- **High-latency networks**: Where server responses may be delayed
- **Load-balanced SMPP servers**: When some nodes may be slower
- **Temporary network issues**: Allows recovery from brief glitches

### Performance Considerations

- **Throughput**: Non-blocking mode provides better throughput under load
- **Latency**: Retries add minimal overhead (200ms × N attempts)
- **Resource Usage**: More efficient than pure blocking mode

### Customization Options

```php
// Alternative configuration examples:

// 1. Faster retries (3 attempts, 100ms delay)
new RetryableReadDecorator(
    new NonBlockingReadStrategy(),
    3,
    100
);

// 2. Hybrid strategy (non-blocking first, then blocking fallback)
new HybridReadStrategy(
    new NonBlockingReadStrategy(),
    new BlockingReadStrategy(500) // 500ms timeout
);
```

### Best Practices

1. Start with 3-5 retries and 100-200ms delay
2. Monitor `SocketTransportException` logs to optimize retry count
3. Consider server-side timeout settings when configuring delays
4. For mission-critical systems, combine with queue-based retry at application level
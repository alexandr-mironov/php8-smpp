# SMPP Connection DSN Format

## Design Philosophy
The DSN (Data Source Name) format was introduced to simplify SMPP connection configuration while enforcing strict typing. 
This replaces the previous approach where:

1. **Before (Problematic)**
```php

// Old way - complicated constructor signature
    /**
     * Construct a new socket for this transport to use.
     *
     * @param array $hosts list of hosts to try.
     * @param mixed $ports list of ports to try, or a single common port
     * @param boolean $persist use persistent sockets
     * @param mixed $debugHandler callback for debug info
     */
    public function __construct(array $hosts, $ports, $persist = false, $debugHandler = null)
    {
        $this->debug = self::$defaultDebug;
        $this->debugHandler = $debugHandler ? $debugHandler : 'error_log';

        // Deal with optional port
        $h = [];
        foreach ($hosts as $key => $host) {
            $h[] = [$host, is_array($ports) ? $ports[$key] : $ports];
        }
        if (self::$randomHost) {
            shuffle($h);
        }
        $this->resolveHosts($h);

        $this->persist = $persist;
    }
```

- Mixed types made validation difficult
- Complex failover configuration

2. **After (DSN Solution)**
```php
// New way - standardized format
ClientBuilder::createForSockets([
    '[2001:db8::1]:2775', // IPv6
    'fallback.host:3550'  // Failover
]);
```
- **Simpler** - Single string format
- **Strictly typed** - Enforced `host:port` structure
- **Safer** - Separates credentials from network details
- **More flexible** - Native support for IPv6/DNS

## Overview
DSN strings provide a standardized way to specify SMPP server connections while keeping authentication 
separate from network configuration.

## Basic Syntax
```
[host][:port]
```

## Supported Formats

### 1. IPv4 Address
```
127.0.0.1:2775
```
- Simple IPv4 with port
- **Valid**: `192.168.1.10:2775`
- **Invalid**: `192.168.1.10` (missing port)

### 2. IPv6 Address (enclosed in brackets)
```
[::1]:2775
```
- **Valid**: `[2001:db8::1]:2775`
- **Invalid**: `2001:db8::1:2775` (missing brackets)

### 3. Domain Name
```
smpp.example.com:2775
```
- DNS resolution will return available IPs
- **Valid**: `gateway.sms-provider.com:2775`
- **Invalid**: `smpp.example.com` (missing port)

## Prohibited Elements
Never include these in DSN:
- ❌ Authentication credentials (`system_id`, `password`)
- ❌ Protocol prefixes (`smpp://`, `tcp://`)
- ❌ Query parameters (`?timeout=5`)
- ❌ Fragments (`#main`)

## Usage Examples

### Creating Client
```php
use Smpp\ClientBuilder;

// Single server
$client = ClientBuilder::createForSockets(['smpp01.example.com:2775']);

// Multiple servers (failover)
$client = ClientBuilder::createForSockets([
    'primary.smpp.com:2775',
    '[2001:db8::1]:2775', // IPv6 fallback
    '192.168.1.100:2775'  // Local fallback
]);
```

## Best Practices
1. **Port Selection**:
    - Standard SMPP port: `2775`
    - Alternative common port: `3550`

2. **DNS Considerations**:
    - DNS resolution returns multiple IPs for hosts
    - IPv6 addresses are prioritized when available

3. **Security**:
   ```php
   // UNSAFE (credentials in DSN)
   'smpp://user:pass@host:2775' // ❌ Never do this!

   // Safe approach
   ->setCredentials('username', 'password') // ✅ Use separate method
   ```
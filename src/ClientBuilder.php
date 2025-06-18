<?php

declare(strict_types=1);


namespace Smpp;

use Generator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Smpp\Configs\SmppConfig;
use Smpp\Configs\SocketTransportConfig;
use Smpp\Contracts\Transport\TransportInterface;
use Smpp\Exceptions\SmppException;
use Smpp\Transport\SCTPTransport;
use Smpp\Transport\SocketTransport;
use Smpp\Utils\Network\DSNParser;
use Smpp\Utils\Network\Entry;

class ClientBuilder
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    /**
     * @var TransportInterface
     */
    private TransportInterface $transport;
    /**
     * @var SmppConfig
     */
    private SmppConfig $config;
    /**
     * @var string
     */
    private string $systemId;
    /**
     * @var string
     */
    private string $password;

    final private function __construct()
    {
        $this->logger = new NullLogger();
        $this->config = new SmppConfig();
    }

    /**
     * @param string[] $dsnEntries
     * @param SocketTransportConfig|null $config
     *
     * @return static
     * @throws Exceptions\SmppInvalidArgumentException
     */
    public static function createForSockets(array $dsnEntries, SocketTransportConfig $config = null): static
    {
        $self = new static();

        if (!isset($config)) {
            $config = new SocketTransportConfig();
        }

        $self->transport = new SocketTransport($self->parseDSNEntries(...$dsnEntries), $config);

        return $self;
    }

    /**
     * @param string ...$dsnEntries
     * @return Entry[]
     *
     * @throws Exceptions\SmppInvalidArgumentException
     */
    private function parseDSNEntries(string ...$dsnEntries): array
    {
        $parsedEntries = [];

        foreach (self::getEntryGenerator($dsnEntries) as $entry) {
            $parsedEntries[] = $entry;
        }

        return $parsedEntries;
    }

    /**
     * @param string[] $dsns
     *
     * @return Generator<int, Entry, mixed, void>
     *   - int: Generator keys (auto-increment)
     *   - Entry: Generated values of type Entry
     *   - mixed: Send type (unused in this generator)
     *   - void: Return type (nothing returned after generation)
     *
     * @throws Exceptions\SmppInvalidArgumentException
     */
    private function getEntryGenerator(array $dsns): Generator
    {
        foreach ($dsns as $dsn) {
            yield from DSNParser::parse($dsn);
        }
    }

    /**
     * @param string[] $dsnEntries
     * @param SocketTransportConfig|null $config
     *
     * @return static
     * @throws Exceptions\SmppInvalidArgumentException
     */
    public static function createForSCTP(array $dsnEntries, SocketTransportConfig $config = null): static
    {
        $self = new static();

        if (!isset($config)) {
            $config = new SocketTransportConfig();
        }

        $self->transport = new SCTPTransport($self->parseDSNEntries(...$dsnEntries), $config);

        return $self;
    }

    /**
     * @param LoggerInterface $logger
     *
     * @return $this
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @param SmppConfig $config
     *
     * @return $this
     */
    public function setClientConfig(SmppConfig $config): self
    {
        $this->config = $config;

        return $this;
    }

    /**
     * @param string $systemId
     * @param string $password
     *
     * @return $this
     */
    public function setCredentials(string $systemId, string $password): self
    {
        $this->systemId = $systemId;
        $this->password = $password;
        return $this;
    }

    /**
     * @return Client
     * @throws SmppException
     */
    public function buildClient(): Client
    {
        if (isset($this->transport->logger) && $this->transport->logger instanceof LoggerInterface) {
            $this->transport->logger = $this->logger;
        }

        $client = new Client(
            $this->transport,
            $this->systemId,
            $this->password,
        );

        $client->logger = $this->logger;
        $client->config = $this->config;

        return $client;
    }
}
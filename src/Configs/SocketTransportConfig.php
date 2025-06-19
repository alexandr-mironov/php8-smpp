<?php

declare(strict_types=1);

namespace Smpp\Configs;


use Smpp\Contracts\Transport\ReadStrategyInterface;
use Smpp\Transport\BlockingReadStrategy;
use Smpp\Transport\HybridReadStrategy;
use Smpp\Transport\NonBlockingReadStrategy;

class SocketTransportConfig
{
    /** @var int */
    private int $defaultSendTimeout = 100;
    /** @var int */
    private int $defaultRecvTimeout = 750;
    /** @var bool */
    private bool $forceIpv6 = false;
    /** @var bool */
    private bool $forceIpv4 = false;
    /** @var bool */
    private bool $randomHost = false;
    /** @var ReadStrategyInterface */
    private ReadStrategyInterface $readStrategy;

    /**
     * @return int
     */
    public function getDefaultSendTimeout(): int
    {
        return $this->defaultSendTimeout;
    }

    /**
     * @param int $defaultSendTimeout
     * @return SocketTransportConfig
     */
    public function setDefaultSendTimeout(int $defaultSendTimeout): SocketTransportConfig
    {
        $this->defaultSendTimeout = $defaultSendTimeout;
        return $this;
    }

    /**
     * @return int
     */
    public function getDefaultRecvTimeout(): int
    {
        return $this->defaultRecvTimeout;
    }

    /**
     * @param int $defaultRecvTimeout
     * @return SocketTransportConfig
     */
    public function setDefaultRecvTimeout(int $defaultRecvTimeout): SocketTransportConfig
    {
        $this->defaultRecvTimeout = $defaultRecvTimeout;
        return $this;
    }

    /**
     * @return bool
     */
    public function isForceIpv6(): bool
    {
        return $this->forceIpv6;
    }

    /**
     * @param bool $forceIpv6
     * @return SocketTransportConfig
     */
    public function setForceIpv6(bool $forceIpv6): SocketTransportConfig
    {
        $this->forceIpv6 = $forceIpv6;
        return $this;
    }

    /**
     * @return bool
     */
    public function isForceIpv4(): bool
    {
        return $this->forceIpv4;
    }

    /**
     * @param bool $forceIpv4
     * @return SocketTransportConfig
     */
    public function setForceIpv4(bool $forceIpv4): SocketTransportConfig
    {
        $this->forceIpv4 = $forceIpv4;
        return $this;
    }

    /**
     * @return bool
     */
    public function isRandomHost(): bool
    {
        return $this->randomHost;
    }

    /**
     * @param bool $randomHost
     * @return SocketTransportConfig
     */
    public function setRandomHost(bool $randomHost): SocketTransportConfig
    {
        $this->randomHost = $randomHost;
        return $this;
    }

    public function getReadStrategy(): ReadStrategyInterface
    {
        return $this->readStrategy ?? new HybridReadStrategy(
                new NonBlockingReadStrategy(),
                new BlockingReadStrategy(500)
            );
    }

    public function setReadStrategy(ReadStrategyInterface $strategy): self
    {
        $this->readStrategy = $strategy;

        return $this;
    }
}
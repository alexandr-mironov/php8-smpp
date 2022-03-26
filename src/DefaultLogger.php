<?php

declare(strict_types=1);

namespace smpp;

/**
 * Class DefaultLogger
 * @package smpp
 */
class DefaultLogger implements LoggerInterface
{
    /**
     * DefaultLogger constructor.
     * @param bool $debug
     */
    public function __construct(public bool $debug = false)
    {

    }

    /**
     * @inheritDoc
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function info($message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $this->debug && error_log($this->buildMessage($level, $message, $context));
    }

    /**
     * @param string $level
     * @param string $message
     * @param array<mixed, mixed> $context
     * @return string
     */
    private function buildMessage(string $level, string $message, array $context = []): string
    {
        $data = ($context) ? ' Data: ' . json_encode($context) : '';
        return '#'
            . (new \DateTime())->format('Y-m-d H:i:s')
            . ' '
            . $level
            . ': '
            . $message
            . $data
            . PHP_EOL;
    }
}
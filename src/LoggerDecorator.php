<?php
declare(strict_types=1);

namespace smpp;


/**
 * Class LogHandler
 * @package smpp
 */
class LoggerDecorator implements LoggerInterface, LoggerAwareInterface
{
    public static bool $debug = false;

    public static int $debugLevel = 0;

    /**
     * @var LoggerInterface[]
     */
    private array $loggers;

    /**
     * LogHandler constructor.
     * @param LoggerInterface ...$loggers
     */
    public function __construct(LoggerInterface ...$loggers)
    {
        $this->loggers = ($loggers) ? $loggers : [new DefaultLogger(self::$debug)];
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->loggers[] = $logger;
    }

    /**
     * @param string $message
     * @param array<mixed, mixed> $context
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
     * @param value-of<LoggerInterface::LEVEL_LIST> $level
     * @param string $message
     * @param array<mixed, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->log($level, $message, $context);
        }
    }
}
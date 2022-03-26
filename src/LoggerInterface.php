<?php

namespace smpp;

use InvalidArgumentException;

/**
 * Describes a logger instance.
 *
 * The message MUST be a string or object implementing __toString().
 *
 * The message MAY contain placeholders in the form: {foo} where foo
 * will be replaced by the context data in key "foo".
 *
 * The context array can contain arbitrary data. The only assumption that
 * can be made by implementors is that if an Exception instance is given
 * to produce a stack trace, it MUST be in a key named "exception".
 *
 * See https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
 * for the full interface specification.
 */
interface LoggerInterface
{
    // log levels (replace to ENUM in php 8.1)
    public const EMERGENCY = 'emergency';
    public const ALERT = 'alert';
    public const CRITICAL = 'critical';
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const NOTICE = 'notice';
    public const INFO = 'info';
    public const DEBUG = 'debug';

    public const LEVEL_LIST = [
        self::ALERT,
        self::CRITICAL,
        self::DEBUG,
        self::EMERGENCY,
        self::ERROR,
        self::INFO,
        self::NOTICE,
        self::WARNING,
    ];

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array<mixed, mixed> $context
     *
     * @return void
     */
    public function emergency(string $message, array $context = []): void;

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array<mixed, mixed> $context
     *
     * @return void
     */
    public function alert(string $message, array $context = []): void;

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array<mixed, mixed> $context
     *
     * @return void
     */
    public function critical(string $message, array $context = []): void;

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array<mixed, mixed> $context
     *
     * @return void
     */
    public function error(string $message, array $context = []): void;

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array<mixed, mixed> $context
     *
     * @return void
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array<mixed, mixed> $context
     *
     * @return void
     */
    public function notice(string $message, array $context = []): void;

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array<mixed, mixed> $context
     *
     * @return void
     */
    public function info($message, array $context = []): void;

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array<mixed, mixed> $context
     *
     * @return void
     */
    public function debug(string $message, array $context = []): void;

    /**
     * Logs with an arbitrary level.
     *
     * @param value-of<LoggerInterface::LEVEL_LIST> $level
     * @param string $message
     * @param array<mixed, mixed> $context
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function log(string $level, string $message, array $context = []): void;
}
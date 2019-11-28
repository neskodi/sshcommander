<?php

namespace Neskodi\SSHCommander\Factories;

use Exception;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Monolog\Logger;

class LoggerFactory
{
    // mapping PSR log levels to Monolog log levels
    const LOG_LEVELS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];

    const DEFAULT_LOG_LEVEL = LogLevel::INFO;

    const LOG_CHANNEL_NAME = 'SSHCommander';

    /**
     * Make a logger instance (Monolog by default).
     *
     * @param SSHConfigInterface $config
     *
     * @return LoggerInterface|null
     *
     * @throws Exception
     */
    public static function makeLogger(SSHConfigInterface $config): ?LoggerInterface
    {
        if (!$file = static::hasWritableLogFile($config)) {
            return null;
        }

        $logLevel = static::getLogLevel($config);
        $logChannelName = $config->get('log_channel_name', static::LOG_CHANNEL_NAME);

        $logger = new Logger($logChannelName);
        $logger->pushHandler(new StreamHandler($file, $logLevel));

        return $logger;
    }

    /**
     * See if user has provided a writable log file to write the log into.
     *
     * @param SSHConfigInterface $config
     *
     * @return string|null
     */
    protected static function hasWritableLogFile(SSHConfigInterface $config): ?string
    {
        if (!$file = $config->get('log_file')) {
            return null;
        }

        if (!is_writable($file)) {
            return null;
        }

        return $file;
    }

    /**
     * Get the log level user has specified in config.
     *
     * @param SSHConfigInterface $config
     *
     * @return int
     */
    protected static function getLogLevel(SSHConfigInterface $config): int
    {
        $level = $config->get('log_level');

        if (is_string($level)) {
            $level = strtolower($level);
        }

        return in_array($level, static::LOG_LEVELS)
            ? $level
            : static::DEFAULT_LOG_LEVEL;
    }
}

<?php /** @noinspection PhpUnusedParameterInspection */

namespace Neskodi\SSHCommander\Factories;

use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Neskodi\SSHCommander\Utils;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Monolog\Logger;
use Exception;

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
        $handlers = static::getHandlers($config);
        if (empty($handlers)) {
            return null;
        }

        $processors = static::getProcessors($config);

        $logChannelName = $config->get('log_channel_name', static::LOG_CHANNEL_NAME);
        $logger = new Logger($logChannelName);

        foreach ($handlers as $handler) {
            $logger->pushHandler($handler);
        }

        foreach ($processors as $processor) {
            $logger->pushProcessor($processor);
        }

        return $logger;
    }

    /**
     * Get the handlers that this logger must use.
     * For more details, see
     * https://github.com/Seldaek/monolog/blob/master/doc/01-usage.md
     *
     * @param SSHConfigInterface $config
     *
     * @return array
     *
     * @throws Exception
     */
    protected static function getHandlers(SSHConfigInterface $config): array
    {
        if (!$file = static::hasWritableLogFile($config)) {
            return [];
        }

        $logLevel = static::getLogLevel($config);
        $formatter = static::getStreamLineFormatter($config);

        return [
            (new StreamHandler($file, $logLevel))->setFormatter($formatter),
        ];
    }

    /**
     * Get the processors that this logger must use.
     * For more details, see
     * https://github.com/Seldaek/monolog/blob/master/doc/01-usage.md
     *
     * @param SSHConfigInterface $config
     *
     * @return array
     */
    protected static function getProcessors(SSHConfigInterface $config): array
    {
        return [
            // This processor is needed to interpolate context into message
            // placeholders like {var}
            new PsrLogMessageProcessor()
        ];
    }

    /**
     * Get the line formatter for the stream log handler (that writes to file).
     * For more details, see
     * https://github.com/Seldaek/monolog/blob/master/doc/01-usage.md
     *
     * @param SSHConfigInterface $config
     *
     * @return FormatterInterface
     */
    protected static function getStreamLineFormatter(SSHConfigInterface $config): FormatterInterface
    {
        // remove the 'context' and 'extra' elements from the standard format
        $output = '[%datetime%] %channel%.%level_name%: %message%' . PHP_EOL;

        $formatter = new LineFormatter($output);

        return $formatter;
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

        if (!Utils::isWritableOrCreatable($file)) {
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
    protected static function getLogLevel(SSHConfigInterface $config): string
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

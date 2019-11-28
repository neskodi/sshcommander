<?php

namespace Neskodi\SSHCommander\Traits;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

trait Loggable
{
    use LoggerTrait;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Write a message to the log.
     *
     * @param int|string $level   One of the PSR3 log levels or Monolog levels
     * @param string     $message The log message
     * @param array      $context replacements for placeholders in message, and
     *                            other additional information that does not fit
     *                            into the message string
     */
    public function log($level, $message, array $context = []): void
    {
        $logger = $this->getLogger();

        if (!$logger instanceof LoggerInterface) {
            return;
        }

        $logger->log($level, $message, $context);
    }

    /**
     * Fluently set the logger instance. Must be a PSR3-compatible logger.
     * By default, monolog is used.
     *
     * @param LoggerInterface $logger
     *
     * @return $this
     */
    protected function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * If this class has a logger, return that instance.
     *
     * @return LoggerInterface|null
     */
    public function getLogger(): ?LoggerInterface
    {
        if (!$this->logger instanceof LoggerInterface) {
            return null;
        }

        return $this->logger;
    }
}

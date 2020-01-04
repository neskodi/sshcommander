<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\ConfigAwareInterface;
use Neskodi\SSHCommander\Interfaces\LoggerAwareInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Traits\ConfigAware;
use Neskodi\SSHCommander\Traits\Loggable;
use Psr\Log\LoggerInterface;

abstract class BaseCommandRunner implements
    LoggerAwareInterface,
    ConfigAwareInterface
{
    use Loggable, ConfigAware;

    /**
     * BaseCommandRunner constructor.
     *
     * @param array|SSHConfigInterface $config
     * @param LoggerInterface|null     $logger
     */
    public function __construct(
        $config,
        ?LoggerInterface $logger = null
    ) {
        $this->setConfig($config);

        if ($logger instanceof LoggerInterface) {
            $this->setLogger($logger);
        }
    }

    abstract public function prepareCommand(SSHCommandInterface $command): SSHCommandInterface;

    abstract public function run(SSHCommandInterface $command);
}

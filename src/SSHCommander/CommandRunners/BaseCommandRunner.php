<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\SSHResultCollectionInterface;
use Neskodi\SSHCommander\Interfaces\ConfigAwareInterface;
use Neskodi\SSHCommander\Interfaces\LoggerAwareInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\SSHResultCollection;
use Neskodi\SSHCommander\Traits\ConfigAware;
use Neskodi\SSHCommander\Traits\Loggable;
use Psr\Log\LoggerInterface;

abstract class BaseCommandRunner implements
    LoggerAwareInterface,
    ConfigAwareInterface
{
    use Loggable, ConfigAware;

    /**
     * @var SSHResultCollectionInterface
     */
    protected $resultCollection;

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

        $this->resultCollection = new SSHResultCollection;
    }

    abstract public function run(SSHCommandInterface $command);
}

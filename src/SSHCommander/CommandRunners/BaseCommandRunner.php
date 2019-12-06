<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\SSHCommanderInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Traits\Loggable;

abstract class BaseCommandRunner
{
    use Loggable;

    /**
     * @var SSHCommanderInterface
     */
    protected $commander;

    /**
     * BaseCommandRunner constructor.
     *
     * @param SSHCommanderInterface $commander
     */
    public function __construct(SSHCommanderInterface $commander)
    {
        $this->commander = $commander;

        if ($logger = $commander->getLogger()) {
            $this->setLogger($logger);
        }
    }

    public function getCommander(): SSHCommanderInterface
    {
        return $this->commander;
    }

    abstract public function run(SSHCommandInterface $command);
}

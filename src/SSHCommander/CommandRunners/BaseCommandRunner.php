<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\CommandInterface;
use Neskodi\SSHCommander\Traits\Loggable;
use Neskodi\SSHCommander\SSHCommander;

abstract class BaseCommandRunner
{
    use Loggable;

    /**
     * @var SSHCommander
     */
    protected $commander;

    /**
     * BaseCommandRunner constructor.
     *
     * @param SSHCommander $commander
     */
    public function __construct(SSHCommander $commander)
    {
        $this->commander = $commander;

        if ($logger = $commander->getLogger()) {
            $this->setLogger($logger);
        }
    }

    public function getCommander(): SSHCommander
    {
        return $this->commander;
    }

    abstract public function run(CommandInterface $command);
}

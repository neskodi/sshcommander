<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\CommandInterface;
use Neskodi\SSHCommander\SSHCommander;
use Neskodi\SSHCommander\Traits\Loggable;

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
    }

    abstract public function run(CommandInterface $command);
}

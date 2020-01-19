<?php

namespace Neskodi\SSHCommander\CommandRunners\Decorators;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;

class CRTimeoutHandlerDecorator
    extends CRBaseDecorator
    implements DecoratedCommandRunnerInterface
{
    /**
     * If the command runner defines any timeout handling behavior, execute it.
     * This decorator ensures that runner will initiate its timeout handling
     * behavior, run the command, then detect the timeout and handle it
     * as appropriate.
     *
     * @param SSHCommandInterface $command
     */
    public function execDecorated(SSHCommandInterface $command): void
    {
        if ($this->hasMethod('setupTimeoutHandler')) {
            $this->setupTimeoutHandler($command);
        }

        $this->runner->execDecorated($command);

        if ($this->hasMethod('handleTimeouts')) {
            $this->handleTimeouts($command);
        }
    }
}

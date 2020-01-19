<?php

namespace Neskodi\SSHCommander\CommandRunners\Decorators;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;

class CRErrorHandlerDecorator
    extends CRBaseDecorator
    implements DecoratedCommandRunnerInterface
{
    /**
     * If the command runner defines any error handling behavior, execute it.
     * E.g. set up any error traps, run the command, and reset the traps
     * afterwards.
     *
     * @param SSHCommandInterface $command
     */
    public function execDecorated(SSHCommandInterface $command): void
    {
        if ($this->hasMethod('setupErrorHandler')) {
            $this->setupErrorHandler($command);
        }

        $this->runner->execDecorated($command);

        if ($this->hasMethod('handleErrors')) {
            $this->handleErrors($command);
        }
    }
}

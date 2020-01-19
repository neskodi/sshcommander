<?php

namespace Neskodi\SSHCommander\CommandRunners\Decorators;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;

class CRTimeoutHandlerDecorator
    extends CRBaseDecorator
    implements DecoratedCommandRunnerInterface
{
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

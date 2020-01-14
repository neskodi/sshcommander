<?php

namespace Neskodi\SSHCommander\CommandRunners\Decorators;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;

class CRErrorHandlerDecorator
    extends CRBaseDecorator
    implements DecoratedCommandRunnerInterface
{
    public function execDecorated(SSHCommandInterface $command): void
    {
        $this->executeOnConnection('trap "echo $?:ERRORMARKER; exit" ERR');

        $this->runner->execDecorated($command);

        $this->executeOnConnection('trap - ERR');
    }
}

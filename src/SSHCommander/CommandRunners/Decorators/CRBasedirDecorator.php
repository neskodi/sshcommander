<?php

namespace Neskodi\SSHCommander\CommandRunners\Decorators;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;

class CRBasedirDecorator
    extends CRBaseDecorator
    implements DecoratedCommandRunnerInterface
{
    public function execDecorated(SSHCommandInterface $command): void
    {
        if ($this->hasMethod('setupBasedir')) {
            $this->setupBasedir($command);
        }

        $this->runner->execDecorated($command);

        if ($this->hasMethod('teardownBasedir')) {
            $this->teardownBasedir($command);
        }
    }
}

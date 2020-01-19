<?php

namespace Neskodi\SSHCommander\CommandRunners\Decorators;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;

class CRBasedirDecorator
    extends CRBaseDecorator
    implements DecoratedCommandRunnerInterface
{
    /**
     * If the command runner defines any basedir handling behavior, execute it.
     * E.g. set the current working directory, run the command and reset the
     * directory afterwards.
     *
     * @param SSHCommandInterface $command
     */
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

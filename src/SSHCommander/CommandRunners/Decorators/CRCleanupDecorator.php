<?php

namespace Neskodi\SSHCommander\CommandRunners\Decorators;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;

class CRCleanupDecorator extends CRBaseDecorator implements DecoratedCommandRunnerInterface
{

    /**
     * Execute channel cleanup routine before running user's command.
     *
     * Since the interactive runner executes all commands in the same channel,
     * by the time we execute the current command there may be output in the
     * channel from the previous ones. This decorator offers the runner the
     * possibility to clear or stash it.
     *
     * @inheritDoc
     */
    public function execDecorated(SSHCommandInterface $command): void
    {
        if ($this->hasMethod('cleanupPreCommand')) {
            $this->cleanupPreCommand($command);
        }

        $this->runner->execDecorated($command);

        if ($this->hasMethod('cleanupPostCommand')) {
            $this->cleanupPostCommand($command);
        }
    }
}

<?php

namespace Neskodi\SSHCommander\CommandRunners\Decorators;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\SSHCommand;

class CRTimeoutHandlerDecorator
    extends CRBaseDecorator
    implements DecoratedCommandRunnerInterface
{
    public function execDecorated(SSHCommandInterface $command): void
    {
        $this->runner->execDecorated($command);

        $this->handleTimeouts($command);
    }

    protected function executeTimeoutBehavior(SSHCommandInterface $command): void
    {
        $behavior = $command->getConfig('timeout_behavior');

        if (!is_string($behavior)) {
            return;
        }

        $this->executeOnConnection(new SSHCommand($behavior));
    }

    protected function executeTimelimitBehavior(SSHCommandInterface $command): void
    {
        $behavior = $command->getConfig('timelimit_behavior');

        if (!is_string($behavior)) {
            return;
        }

        $this->executeOnConnection(new SSHCommand($behavior));
    }

    /**
     * @param SSHCommandInterface $command
     */
    protected function handleTimeouts(SSHCommandInterface $command): void
    {
        if ($this->getConnection()->isTimeout()) {
            $this->executeTimeoutBehavior($command);
        } elseif ($this->getConnection()->isTimelimit()) {
            $this->executeTimelimitBehavior($command);
        }
    }
}

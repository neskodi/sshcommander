<?php

namespace Neskodi\SSHCommander\CommandRunners\Decorators;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Traits\Timer;

class CRTimerDecorator
    extends CRBaseDecorator
    implements DecoratedCommandRunnerInterface
{
    use Timer;

    /**
     * Stopwatch command start and end time.
     *
     * @param SSHCommandInterface $command
     */
    public function execDecorated(SSHCommandInterface $command): void
    {
        $this->startTimer();

        $this->runner->execDecorated($command);

        $this->stopTimer();
    }
}

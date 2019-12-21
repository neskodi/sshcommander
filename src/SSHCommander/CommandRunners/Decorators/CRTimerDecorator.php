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
    public function exec(SSHCommandInterface $command): void
    {
        $this->startTimer();

        $this->runner->exec($command);

        $this->stopTimer();
    }
}

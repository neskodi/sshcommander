<?php

namespace Neskodi\SSHCommander\CommandRunners\Decorators;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;

class CRLoggerDecorator
    extends CRBaseDecorator
    implements DecoratedCommandRunnerInterface
{
    /**
     * Log command start and end.
     *
     * @param SSHCommandInterface $command
     */
    public function execDecorated(SSHCommandInterface $command): void
    {
        $this->logCommandStart($command);

        $this->runner->execDecorated($command);

        $this->logCommandEnd();
    }

    /**
     * Log the event of running the command.
     *
     * The call to 'info' is delegated by the magic __call method to the
     * original CommandRunner class that implements the LoggerAware interface.
     *
     * @param SSHCommandInterface $command
     */
    protected function logCommandStart(SSHCommandInterface $command): void
    {
        $this->info(sprintf(
                'Running command: %s',
                $command->toLoggableString())
        );
    }

    /**
     * Log command completion along with the time it took to run.
     *
     * The calls to the timer and logging methods are delegated by __call to the
     * original CommandRunner class that implements the LoggerAware interface.
     */
    protected function logCommandEnd(): void
    {
        $seconds = $this->runner->getElapsedTime();

        if ($this->getConnection()->isTimeout()) {
            $this->notice('Command timed out after {seconds} seconds', [
                'seconds' => $seconds,
            ]);
        } else {
            $this->info('Command completed in {seconds} seconds', [
                'seconds' => $seconds,
            ]);
        }
    }
}

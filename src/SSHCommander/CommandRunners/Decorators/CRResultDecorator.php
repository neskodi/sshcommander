<?php

namespace Neskodi\SSHCommander\CommandRunners\Decorators;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\SSHCommandResult;

class CRResultDecorator
    extends CRBaseDecorator
    implements DecoratedCommandRunnerInterface
{
    /**
     * Create the result object and populate it with the results of command run.
     *
     * @param SSHCommandInterface $command
     */
    public function exec(SSHCommandInterface $command): void
    {
        $result = $this->createResult($command);

        $this->runner->exec($command);

        $this->recordCommandResults($command, $result);
        $this->recordCommandTiming($result);
        $result->logResult();

        $this->setResult($result);
    }

    /**
     * Collect the execution result into the result object.
     *
     * @param SSHCommandInterface $command
     *
     * @return SSHCommandResultInterface
     */
    protected function createResult(
        SSHCommandInterface $command
    ): SSHCommandResultInterface {
        // construct the result
        $result = new SSHCommandResult($command);

        if ($logger = $this->getLogger()) {
            $result->setLogger($logger);
        }

        return $result;
    }

    /**
     * Save command exit code, stdout and stderr into the result object.
     *
     * @param SSHCommandInterface       $command
     * @param SSHCommandResultInterface $result
     */
    protected function recordCommandResults(
        SSHCommandInterface $command,
        SSHCommandResultInterface $result
    ): void {
        $connection = $this->getConnection();

        $result->setExitCode($connection->getLastExitCode())
               ->setOutput($connection->getStdOutLines());

        // get the error stream separately, if we were asked to
        if ($command->getConfig('separate_stderr')) {
            $result->setErrorOutput($connection->getStdErrLines());
        }
    }

    /**
     * Record command start and end timestamps and the elapsed time into
     * command result object.
     *
     * @param SSHCommandResultInterface $result
     */
    protected function recordCommandTiming(
        SSHCommandResultInterface $result
    ): void {
        $result->setCommandStartTime($this->getTimerStart())
               ->setCommandEndTime($this->getTimerEnd())
               ->setCommandElapsedTime($this->getElapsedTime())
               ->setIsTimeout($this->getConnection()->isTimeout());
    }
}

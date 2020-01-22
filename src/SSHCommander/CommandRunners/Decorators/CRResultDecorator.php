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
    public function execDecorated(SSHCommandInterface $command): void
    {
        $result = $this->createResult($command);

        $this->runner->execDecorated($command);

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
     * Record command start and end timestamps and the elapsed time into
     * command result object.
     *
     * @param SSHCommandResultInterface $result
     */
    protected function recordCommandTiming(
        SSHCommandResultInterface $result
    ): void {
        // timer methods are delegated down the decorator chain and are executed
        // by CRTimerDecorator
        $result->setCommandStartTime($this->getTimerStart())
               ->setCommandEndTime($this->getTimerEnd())
               ->setCommandElapsedTime($this->getElapsedTime())
               ->setIsTimeout($this->getConnection()->isTimeout())
               ->setIsTimelimit($this->getConnection()->isTimelimit());
    }
}

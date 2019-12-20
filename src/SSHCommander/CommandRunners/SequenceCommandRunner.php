<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHRemoteCommandRunnerInterface;

class SequenceCommandRunner
    extends RemoteCommandRunner
    implements SSHRemoteCommandRunnerInterface
{
    /**
     * Run the command.
     *
     * @param SSHCommandInterface $command the object containing the command to
     *                                     run
     *
     * @return SSHCommandResultInterface
     *
     * @throws AuthenticationException
     */
    public function run(SSHCommandInterface $command): SSHCommandResultInterface
    {
        // check that the connection is set and is ready to run the command
        // configure it to respect specific command settings
        $this->validateConnection()
             ->prepareConnection($command)

        // and fluently execute the command.
             ->exec($command);

        // reset connection to the default configuration, such as default
        // timeout and quiet mode
        $this->resetConnection();

        // collect, log and return the results
        $result = $this->collectResult($command);
        $result->logResult();
        $this->resultCollection[] = $result;

        return $result;
    }

    /**
     * Execute command using the timer and logger.
     *
     * @param $command
     */
    protected function exec($command)
    {
        $this->logCommandStart($command);
        $this->startTimer();

        $this->getConnection()->exec($command);

        // stop the timer and log command end
        $this->logCommandEnd($this->stopTimer());
    }
}

<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandRunnerInterface;
use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Exceptions\CommandRunException;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\SSHCommandResult;

class RemoteCommandRunner extends BaseCommandRunner
    implements SSHCommandRunnerInterface
{
    /**
     * @var SSHConnectionInterface
     */
    protected $connection;

    /**
     * Run the command.
     *
     * @param SSHCommandInterface $command the object containing the command to
     *                                     run
     *
     * @return SSHCommandResultInterface
     *
     * @throws CommandRunException
     * @throws AuthenticationException
     */
    public function run(SSHCommandInterface $command): SSHCommandResultInterface
    {
        // retrieve and set a connection instance if it's not yet there
        // configure it to respect specific command settings
        $this->prepareConnection($command)
             ->getConnection()
             ->exec($command);

        $result = $this->collectResult($command);

        $this->logResult($result);

        return $result;
    }

    /**
     * Get the SSH Connection instance used by this command runner.
     *
     * @return SSHConnectionInterface
     *
     * @throws AuthenticationException
     */
    public function getConnection(): SSHConnectionInterface
    {
        // if user hasn't injected their own connection instance until now,
        // ask the commander for one
        if (!$this->connection) {
            $this->setConnection($this->getCommander()->getConnection());
        }

        return $this->connection;
    }

    /**
     * Set the connection to run the command on.
     *
     * @param SSHConnectionInterface $connection
     *
     * @return $this
     */
    public function setConnection(SSHConnectionInterface $connection
    ): SSHCommandRunnerInterface {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Fluently prepare the connection according to command config.
     *
     * @param SSHCommandInterface $command
     *
     * @return $this
     *
     * @throws AuthenticationException
     */
    protected function prepareConnection(SSHCommandInterface $command
    ): SSHCommandRunnerInterface {
        // if user wants stderr as separate stream or wants to suppress it
        // altogether, tell phpseclib about it
        if (
            $command->getOption('separate_stderr') ||
            $command->getOption('suppress_stderr')
        ) {
            $this->getConnection()->getSSH2()->enableQuietMode();
        }

        return $this;
    }

    /**
     * Collect the execution result into the result object.
     *
     * @param SSHCommandInterface $command
     *
     * @return SSHCommandResultInterface
     *
     * @throws AuthenticationException
     */
    protected function collectResult(
        SSHCommandInterface $command
    ): SSHCommandResultInterface {
        $conn = $this->getConnection();

        // structure the result
        $result = new SSHCommandResult($command);

        if ($logger = $this->getLogger()) {
            $result->setLogger($logger);
        }

        $result->setExitCode($conn->getLastExitCode())
               ->setOutput($conn->getStdOutLines());

        // get the error stream separately, if we were asked to
        if ($command->getOption('separate_stderr')) {
            $result->setErrorOutput($conn->getStdErrLines());
        }

        return $result;
    }

    /**
     * Log command exit code and output:
     * - notice / info: only exit code in case of error
     * - debug: any exit code and entire output
     *
     * @param SSHCommandResultInterface $result
     */
    protected function logResult(SSHCommandResultInterface $result): void
    {
        $status = $result->getStatus();
        $code   = $result->getExitCode();
        if ($result->isError()) {
            // error is logged on the notice level
            $this->notice(
                'Command returned error status: {status}',
                ['status' => $result->getExitCode()]
            );
        } else {
            // success is logged on the debug level only
            $this->debug(
                'Command returned exit status: {status} (code {code})',
                compact('status', 'code')
            );
        }

        // log the entire command output (debug level only)
        $result->logResult();
    }
}

<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\SSHRemoteCommandRunnerInterface;
use Neskodi\SSHCommander\Exceptions\ConnectionMissingException;
use Neskodi\SSHCommander\Exceptions\InvalidConnectionException;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\SSHCommandResult;
use Neskodi\SSHCommander\Traits\Timer;

class RemoteCommandRunner
    extends BaseCommandRunner
    implements SSHRemoteCommandRunnerInterface
{
    use Timer;

    /**
     * @var SSHConnectionInterface
     */
    protected $connection;

    /**
     * Get the SSH Connection instance used by this command runner.
     *
     * @return null|SSHConnectionInterface
     */
    public function getConnection(): ?SSHConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Set the connection to run the command on.
     *
     * @param SSHConnectionInterface $connection
     *
     * @return $this
     */
    public function setConnection(
        SSHConnectionInterface $connection
    ): SSHRemoteCommandRunnerInterface {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Run the command.
     *
     * @param SSHCommandInterface $command the object containing the command to
     *                                     run
     *
     * @return SSHCommandResultInterface
     */
    public function run(SSHCommandInterface $command): SSHCommandResultInterface
    {
        // normal runner only stores one result per run
        $this->resultCollection->wipe();

        // check that the connection is set and is ready to run the command
        // configure it to respect specific command settings
        $this->validateConnection()
             ->prepareConnection($command);

        // prepare the result
        $result = $this->createResult($command);

        // log the event of command start and start the timer.
        $this->logCommandStart($command);
        $this->startTimer();

        // and fluently execute the command.
        $this->exec($command);

        // stop the timer and log command end
        $this->stopTimer();
        $this->logCommandEnd();

        // collect and log the result information
        $this->recordCommandResults($command, $result);
        $this->recordCommandTiming($result);
        $result->logResult();

        // reset connection to the default configuration, such as default
        // timeout and quiet mode
        $this->resetConnection();

        $this->resultCollection[] = $result;

        return $result;
    }

    /**
     * Since we can't accept connection as an argument to constructor, we do
     * late injection and validation.
     *
     * Check that the connection is set, is authenticated or at least contains
     * valid config for authentication attempt.
     *
     * @return $this
     */
    protected function validateConnection(): SSHCommandRunnerInterface
    {
        $connection = $this->getConnection();

        if (!$connection instanceof SSHConnectionInterface) {
            throw new ConnectionMissingException(
                'SSH command runner requires an SSH connection object, ' .
                'none was provided');
        }

        if (!$connection->isAuthenticated() && !$connection->isValid()) {
            throw new InvalidConnectionException(
                'SSH connection object provided to command runner is ' .
                'misconfigured');
        }

        return $this;
    }

    /**
     * Fluently prepare the connection according to command config.
     *
     * @param SSHCommandInterface $command
     *
     * @return $this
     */
    protected function prepareConnection(
        SSHCommandInterface $command
    ): SSHCommandRunnerInterface {
        $connection = $this->getConnection();

        // Enforce config from the command
        $connection->setConfig($command->getConfig());

        // authenticate if necessary. We do it early so that any incurring time
        // does not count towards command execution time in the logs.
        if (!$connection->isAuthenticated()) {
            $connection->authenticate();
        }

        // if user wants stderr as separate stream or wants to suppress it
        // altogether, tell phpseclib about it
        if (
            $command->getConfig('separate_stderr') ||
            $command->getConfig('suppress_stderr')
        ) {
            $connection->enableQuietMode();
        }

        // Set command timeout
        $connection->setTimeout($command->getConfig('timeout_command'));

        return $this;
    }

    /**
     * Execute the command on the prepared connection.
     *
     * @param $command
     */
    protected function exec($command)
    {
        $this->getConnection()->exec($command);
    }

    /**
     * Reset connection to the default configuration, such as default
     * timeout and quiet mode.
     */
    protected function resetConnection(): void
    {
        $this->getConnection()->resetCommandConfig();
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
     *
     * @return SSHCommandResultInterface
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
     *
     * @return SSHCommandResultInterface
     */
    protected function recordCommandTiming(
        SSHCommandResultInterface $result
    ): void {
        $result->setCommandStartTime($this->timerStart)
               ->setCommandEndTime($this->timerEnd)
               ->setCommandElapsedTime($this->getElapsedTime())
               ->setIsTimeout($this->getConnection()->getSSH2()->isTimeout());
    }

    /**
     * Log the event of running the command.
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
     * @param float $seconds
     */
    protected function logCommandEnd(): void
    {
        $seconds = $this->getElapsedTime();

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

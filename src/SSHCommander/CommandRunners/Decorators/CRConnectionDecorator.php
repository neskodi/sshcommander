<?php

namespace Neskodi\SSHCommander\CommandRunners\Decorators;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Exceptions\ConnectionMissingException;
use Neskodi\SSHCommander\Exceptions\InvalidConnectionException;
use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;

class CRConnectionDecorator
    extends CRBaseDecorator
    implements DecoratedCommandRunnerInterface
{
    /**
     * Before running the command, we check that the connection is set and is
     * ready to run the command. Also we configure it to respect the specific
     * command settings.
     *
     * After running the command, we reset the connection back to default
     * settings.
     *
     * @param SSHCommandInterface $command
     */
    public function execDecorated(SSHCommandInterface $command): void
    {
        $this->validateConnection()
             ->prepareConnection($command);

        $this->runner->execDecorated($command);

        // reset connection to the default configuration, such as default
        // timeout and quiet mode
        $this->resetConnection();
    }

    /**
     * Check that the connection is set, is authenticated or at least contains
     * valid config for authentication attempt.
     *
     * @return $this
     */
    protected function validateConnection()
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
    ) {
        $connection = $this->getConnection();

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
     * Reset connection to the default configuration, such as default
     * timeout and quiet mode.
     */
    protected function resetConnection(): void
    {
        $this->getConnection()->resetCommandConfig();
    }
}

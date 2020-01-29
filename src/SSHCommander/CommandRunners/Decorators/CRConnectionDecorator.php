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
             ->setQuietMode($command)
             ->setTimeout($command)
             ->authenticateConnection()
             ->examineConnectionFeatures();

        $this->runner->execDecorated($command);
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
     * Authenticate the connection if it hasn't been authenticated previously.
     *
     * @return $this
     */
    protected function authenticateConnection()
    {
        $this->getConnection()->authenticateIfNecessary();

        return $this;
    }

    /**
     * Test support for various features, such as system_timeout, job control
     * etc.
     *
     * @return $this
     */
    protected function examineConnectionFeatures()
    {
        $connection = $this->getConnection();

        if (!$connection->isExamined()) {
            $connection->examine();
        }

        return $this;
    }

    /**
     * If command configuration requires, set the quiet mode.
     *
     * @param SSHCommandInterface $command
     *
     * @return $this
     */
    protected function setQuietMode(SSHCommandInterface $command)
    {
        if (
            $command->getConfig('separate_stderr') ||
            $command->getConfig('suppress_stderr')
        ) {
            $this->getConnection()->enableQuietMode();
        }

        return $this;
    }

    /**
     * Set the requested timeout on SSH2 object.
     *
     * @param SSHCommandInterface $command
     *
     * @return $this
     */
    protected function setTimeout(SSHCommandInterface $command)
    {
        $this->getConnection()->setTimeout(
            $command->getConfig('timeout_command')
        );

        return $this;
    }
}

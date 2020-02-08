<?php

namespace Neskodi\SSHCommander\CommandRunners\Decorators;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Utils;

class CRTimeoutHandlerDecorator
    extends CRBaseDecorator
    implements DecoratedCommandRunnerInterface
{
    /**
     * If the command runner defines any timeout handling behavior, execute it.
     * This decorator ensures that runner will initiate its timeout handling
     * behavior, run the command, then detect the timeout and handle it
     * as appropriate.
     *
     * @param SSHCommandInterface $command
     */
    public function execDecorated(SSHCommandInterface $command): void
    {
        $this->setupTimeoutHandler($command);

        $this->runner->execDecorated($command);
    }

    /**
     * Set the functions responsible for detecting timeout condition and handling
     * it in a proper manner.
     *
     * @param SSHCommandInterface $command
     *
     * @noinspection PhpMethodParametersCountMismatchInspection
     */
    public function setupTimeoutHandler(SSHCommandInterface $command): void
    {
        $connection   = $this->getConnection();
        $timeoutValue = $command->getConfig('timeout');

        $connection->setTimeout($timeoutValue);

        if ($timeoutValue) {
            $connection->getSSH2()->configureTimeouts(
                0.5,
                $this->getTimeoutWatcherFunction($command),
                $this->getTimeoutHandlerFunction($command)
            );
        } else {
            $this->disableTimeoutHandling();
        }
    }

    /**
     * Generate the function that will be used to watch for timelimit condition
     *
     * @return callable
     */
    protected function getTimeoutWatcherFunction(): callable
    {
        return function () {
            $connection = $this->getConnection();

            return $connection->reachedTimeout() ||
                   $connection->reachedTimeLimit();
        };
    }

    /**
     * Generate the function that will be called in case of timelimit condition.
     *
     * @param SSHCommandInterface $command
     *
     * @return callable
     */
    protected function getTimeoutHandlerFunction(SSHCommandInterface $command): callable
    {
        return function () use ($command) {
            $this->executeTimeoutBehavior($command);
        };
    }

    /**
     * Tell the underlying SSH2 instance to disable any timeout watching / handling
     * behavior.
     */
    protected function disableTimeoutHandling()
    {
        $this->getConnection()->getSSH2()->configureTimeouts(null, null, null);
    }

    /**
     * Execute the behavior user has set up for the timeout situations (i.e. for
     * situations when SSH connection is waiting for command output longer than
     * allowed). User can define this behavior by setting e.g.
     * 'timeout_behavior' => SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE
     * in the command config.
     *
     * By default, no action is taken.
     *
     * @param SSHCommandInterface $command
     */
    protected function executeTimeoutBehavior(SSHCommandInterface $command): void
    {
        $behavior   = $command->getConfig('timeout_behavior');
        $connection = $this->getConnection();

        // disable any timeout handler so that if we want to read from the connection
        // again, we don't fall into endless nesting loop
        $this->disableTimeoutHandling();

        // if user wants to send a control character such as CTRL+C, just send it
        if (Utils::isAsciiControlCharacter($behavior)) {
            $connection->write($behavior);
            $connection->cleanCommandBuffer();

            return;
        }

        // if user wants to send a custom command, ensure \n in the end
        if (is_string($behavior)) {
            $connection->writeAndSend($behavior);
            $connection->cleanCommandBuffer();

            return;
        }

        // if user has specified a callable, execute it
        if (is_callable($behavior)) {
            $behavior($connection, $command);

            return;
        }
    }
}

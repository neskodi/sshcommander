<?php

namespace Neskodi\SSHCommander\CommandRunners\Decorators;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\SSHConfig;
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

        $this->handleTimeouts($command);
    }

    public function setupTimeoutHandler(SSHCommandInterface $command): void
    {
        $connection   = $this->getConnection();
        $timeoutValue = $command->getConfig('timeout');

        $connection->setTimeout($timeoutValue);
    }

    /**
     * If the command has timed out by running time condition, it may mean that
     * the connection is left with an unfinished command. In this case, we
     * need to execute the timeout behavior, if user has configured one.
     *
     * The most common example is to send CTRL+C to cancel command execution,
     * which is achieved by setting
     * 'timeout_behavior' => SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE
     * in the command config. This is also the default behavior.
     *
     * @param SSHCommandInterface $command
     *
     * @noinspection PhpUnused
     */
    public function handleTimeouts(SSHCommandInterface $command): void
    {
        if (
            SSHConfig::TIMEOUT_CONDITION_RUNTIME === $command->getConfig('timeout_condition') &&
            $this->getConnection()->isTimelimit()
        ) {
            $this->executeTimeoutBehavior($command);
        }
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

    /**
     * Execute the user command through the system built-in timeout utility.
     *
     * Remember that user's command may be compound, like "command 1; command 2"
     * hence the complexity.
     *
     * @param SSHCommandInterface $command
     */
    public function wrapCommandIntoTimeout(SSHCommandInterface $command): void
    {
        // produce 'timeout --preserve-status 10'
        $timeoutCmd = sprintf(
            'timeout --preserve-status %d',
            $command->getConfig('timeout')
        );

        // this will produce e.g. 'bash' when run on command line
        $detectShellNameCmd = '`ps -p $$ -ocomm=`';

        // user command will be wrapped into unique markers to prevent conflict
        // with any character sequence that might occur inside user's command
        $hereDocMarker = uniqid();
        $hereDoc       = "<<$hereDocMarker\n%s\n$hereDocMarker";

        $pattern = "$timeoutCmd $detectShellNameCmd $hereDoc";

        $command->wrap($pattern);
    }
}

<?php

namespace Neskodi\SSHCommander\CommandRunners\Decorators;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;

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

        if ($this->hasMethod('handleTimeouts')) {
            $this->handleTimeouts($command);
        }
    }

    public function setupTimeoutHandler(SSHCommandInterface $command): void
    {
        $connection   = $this->getConnection();
        $timeoutValue = $command->getConfig('timeout');

        $connection->setTimeout($timeoutValue);
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
        $hereDoc = "<<$hereDocMarker\n%s\n$hereDocMarker";

        $pattern = "$timeoutCmd $detectShellNameCmd $hereDoc";

        $command->wrap($pattern);
    }
}

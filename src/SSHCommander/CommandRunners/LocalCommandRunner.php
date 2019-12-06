<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\SSHCommandResult;

class LocalCommandRunner extends BaseCommandRunner implements SSHCommandRunnerInterface
{
    /**
     * Run the command.
     *
     * @param SSHCommandInterface $command the object containing the command to run
     *
     * @return SSHCommandResultInterface
     */
    public function run(SSHCommandInterface $command): SSHCommandResultInterface
    {
        $output = [];
        $code   = 0;

        exec($command, $output, $code);

        $result = new SSHCommandResult($command);
        $result->setOutput($output)
               ->setExitCode($code);

        return $result;
    }
}

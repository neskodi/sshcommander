<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\CommandResultInterface;
use Neskodi\SSHCommander\Interfaces\CommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\CommandInterface;
use Neskodi\SSHCommander\CommandResult;

class LocalCommandRunner extends BaseCommandRunner implements CommandRunnerInterface
{
    /**
     * Run the command.
     *
     * @param CommandInterface $command the object containing the command to run
     *
     * @return CommandResultInterface
     */
    public function run(CommandInterface $command): CommandResultInterface
    {
        $output = [];
        $return = 0;

        exec($command, $output, $return);

        return new CommandResult($command, $return, $output);
    }
}

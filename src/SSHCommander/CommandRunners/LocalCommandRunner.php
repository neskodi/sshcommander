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
        $code   = 0;

        exec($command, $output, $code);

        $result = [
            'exitcode' => $code,
            'out'      => $output,
        ];

        return new CommandResult($command, $result);
    }
}

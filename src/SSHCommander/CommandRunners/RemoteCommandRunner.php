<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Interfaces\CommandResultInterface;
use Neskodi\SSHCommander\Interfaces\CommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\CommandInterface;
use Neskodi\SSHCommander\CommandResult;

class RemoteCommandRunner extends BaseCommandRunner implements CommandRunnerInterface
{
    /**
     * Run the command.
     *
     * @param CommandInterface $command the object containing the command to run
     *
     * @return CommandResultInterface
     *
     * @throws AuthenticationException
     */
    public function run(CommandInterface $command): CommandResultInterface
    {
        $conn   = $this->commander->getConnection();
        $delim  = $this->commander->getConfig()->get('delimiter_join_output');

        $return = $conn->exec($command);

        $result = new CommandResult($command, $return);
        $result->setOutputDelimiter($delim);

        return $result;
    }
}

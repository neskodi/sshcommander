<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Interfaces\CommandResultInterface;
use Neskodi\SSHCommander\Interfaces\CommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\CommandInterface;

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

        $result = $conn->exec($command);
        $result->setOutputDelimiter($delim);

        return $result;
    }
}

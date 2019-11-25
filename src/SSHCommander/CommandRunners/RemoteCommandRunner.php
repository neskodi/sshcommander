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
        $delimS = $this->commander->getConfig()->get('delimiter_split_output');
        $delimJ = $this->commander->getConfig()->get('delimiter_join_output');

        $result = [];

        $conn->exec($command, function ($str) use ($delimS, &$result) {
            $result[] = explode($delimS, $str);
        });

        $code = $conn->getSSH2()->getExitStatus();
        $result = new CommandResult($command, $code, $result);

        return $result->setOutputDelimiter($delimJ);
    }
}

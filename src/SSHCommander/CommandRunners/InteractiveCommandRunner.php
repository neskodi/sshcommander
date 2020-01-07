<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Exceptions\CommandRunException;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Traits\HasConnection;
use Neskodi\SSHCommander\Traits\HasResult;

class InteractiveCommandRunner
    extends BaseCommandRunner
{
    use HasConnection;
    use HasResult;

    /**
     * @var array
     */
    protected $options = [];

    protected $marker = 'NOSCE TE IPSUM, ET NOSCES UNIVERSUM ET DEOS';

    /**
     * Run the command and save the result to the collection.
     *
     * @param SSHCommandInterface $command the object containing the command to
     *                                     run
     *
     * @return SSHCommandResultInterface
     * @throws CommandRunException
     */
    public function run(SSHCommandInterface $command): SSHCommandResultInterface
    {
        $result = parent::run($command);

        $this->readCommandExitCode($result);

        if ($result->isError() && $this->getConfig('break_on_error')) {
            throw new CommandRunException;
        }

        return $result;
    }

    /**
     * Execute the command on the prepared connection.
     *
     * @param SSHCommandInterface $command
     */
    public function exec(SSHCommandInterface $command): void
    {
        $this->getConnection()->execInteractive(
            $command,
            $this->getEndMarkerRegex()
        );
    }

    /**
     * Prepend preliminary commands to the main command according to main
     * command configuration. If any preparation is necessary, such as moving
     * into basedir or setting the errexit option before running the main
     * command, another instance will be returned that contains the prepended
     * extra commands.
     *
     * @param SSHCommandInterface $command
     *
     * @return SSHCommandInterface
     */
    public function prepareCommand(SSHCommandInterface $command): SSHCommandInterface
    {
        $prepared = parent::prepareCommand($command);

        $this->appendCommandEndMarker($prepared);

        return $prepared;
    }

    /**
     * Generate a unique command end marker so we can catch it in the shell
     * output and know when to continue. In this output, we'll also capture the
     * exit code of the last command in user's input.
     *
     * @param SSHCommandInterface $command
     */
    protected function appendCommandEndMarker(SSHCommandInterface $command)
    {
        $this->marker = uniqid();

        $append = sprintf('echo "$?:%s"', $this->marker);

        $command->appendCommand($append);
    }

    protected function getEndMarkerRegex()
    {
        $regex = '([\s\t\r\n]+|\d+):' . $this->marker;
        $regex = sprintf('/%s/', $regex);

        return $regex;
    }

    protected function readCommandExitCode(SSHCommandResultInterface $result): void
    {
        $outputLines = $result->getOutput();

        if (!count($outputLines)) {
            // we won't be able to read the code
            return;
        }

        $matches = [];
        preg_match_all($this->getEndMarkerRegex(), end($outputLines), $matches);

        if (empty($matches[0])) {
            // end marker is not found in command output
            return;
        }

        if (is_numeric($matches[1][0])) {
            // this is the exit code
            $result->setOutput(array_slice($outputLines, 0, -1));
            $result->setExitCode((int)$matches[1][0]);
        }
    }
}

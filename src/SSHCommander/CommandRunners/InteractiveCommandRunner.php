<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Exceptions\CommandRunException;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;

class InteractiveCommandRunner
    extends BaseCommandRunner
{
    /**
     * @var array
     */
    protected $options = [];

    protected $marker = '';

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
        // set the marker to a unique string
        $this->marker = uniqid();

        // make shell display the last command exit code and the marker
        $append = sprintf('echo "$?:%s"', $this->marker);

        // append to command
        $command->appendCommand($append);
    }

    /**
     * Get the regular expression used to detect our marker in the output
     * produced by the command.
     *
     * @return string|null
     */
    protected function getEndMarkerRegex(): ?string
    {
        if (empty($this->marker)) {
            return null;
        }

        $regex = '(\d+):' . $this->marker;
        $regex = sprintf('/%s/', $regex);

        return $regex;
    }

    /**
     * Try to find our end command marker in command result and detect the
     * last command exit code.
     *
     * @param SSHCommandResultInterface $result
     */
    protected function readCommandExitCode(SSHCommandResultInterface $result): void
    {
        if (empty($this->marker)) {
            // we won't be able to read the code
            return;
        }

        $outputLines = $result->getOutput();

        if (!count($outputLines)) {
            // we won't be able to read the code
            return;
        }

        $matches = [];
        $lastLine = end($outputLines);
        preg_match_all($this->getEndMarkerRegex(), $lastLine, $matches);

        if (empty($matches[0])) {
            // we have provided the end marker to the runner but it is not found
            // in the final output.
            // we assume something bad happened in the middle of command
            // execution and we consider this command failed.
            $exitCode = intval($lastLine) ?: 1;
            $result->setExitCode($exitCode);
        } elseif (is_numeric($matches[1][0])) {
            // this is the exit code
            $result->setExitCode((int)$matches[1][0]);
        }

        // finally, remove that last line from the output
        $result->setOutput(array_slice($outputLines, 0, -1));
    }
}

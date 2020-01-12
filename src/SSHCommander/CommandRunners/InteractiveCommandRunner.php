<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\SSHConfig;

class InteractiveCommandRunner
    extends BaseCommandRunner
{
    /**
     * @var array
     */
    protected $options = [];

    protected $marker = '';

    public function run(SSHCommandInterface $command): SSHCommandResultInterface
    {
        // ensure a unique command end marker for each run
        $this->createEndMarker();

        return parent::run($command);
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

    public function recordCommandResults(
        SSHCommandInterface $command,
        SSHCommandResultInterface $result
    ): void {
        $outputLines = $this->getStdOutLines($command);
        $exitCode    = $this->readCommandExitCode($outputLines);

        $result->setOutput($outputLines)
               ->setErrorOutput($this->getStdErrLines($command))
               ->setExitCode($exitCode);
    }

    public function getLastExitCode(SSHCommandInterface $command): ?int
    {
        $outputLines = $this->getStdOutLines($command);

        return $this->readCommandExitCode($outputLines);
    }

    public function getStdOutLines(SSHCommandInterface $command): array
    {
        return $this->getConnection()->getStdOutLines();
    }

    public function getStdErrLines(SSHCommandInterface $command): array
    {
        // The interactive runner can't afford the luxury of having the separate
        // error stream
        return [];
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

    protected function createEndMarker()
    {
        $this->marker = uniqid();
    }

    protected function getEndMarker()
    {
        if (!$this->marker) {
            $this->createEndMarker();
        }

        return $this->marker;
    }

    protected function getEndMarkerEchoCommand()
    {
        $marker = $this->getEndMarker();

        return sprintf('echo "$?:%s"', $marker);
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
        // make shell display the last command exit code and the marker
        $append = $this->getEndMarkerEchoCommand();

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

        $regex = '(\d+):' . $this->getEndMarker();
        $regex = sprintf('/%s/', $regex);

        return $regex;
    }

    /**
     * Try to find our end command marker in command result and detect the
     * last command exit code.
     *
     * @param array $outputLines
     *
     * @return int
     */
    protected function readCommandExitCode(array &$outputLines = []): ?int
    {
        if (empty($this->marker) || !count($outputLines)) {
            // we won't be able to read the code
            return null;
        }

        $regex   = $this->getEndMarkerRegex();
        $matches = [];

        foreach ($outputLines as $i => $line) {
            preg_match_all($regex, $line, $matches);

            if (!empty($matches[0])) {
                // this is the exit code
                // remove that line from the output
                unset($outputLines[$i]);

                return (int)$matches[1][0];
            }
        }

        // we have provided the end marker to the runner but it is not found
        // in the final output.
        // we assume something bad happened in the middle of command
        // execution and we consider this command failed.
        return intval(end($outputLines)) ?: 1;
    }

    /**
     * Prepend 'set -e' to the command if user wants to always break on error.
     *
     * @param SSHCommandInterface $command
     */
    protected function prependErrexit(SSHCommandInterface $command): void
    {
        if (SSHConfig::BREAK_ON_ERROR_ALWAYS === $command->getConfig('break_on_error')) {
            // add a trap so we can detect the exit code
            $endMarkerCommand = $this->getEndMarkerEchoCommand();
            $command->prependCommand("trap '$endMarkerCommand' ERR");

            // turn on errexit mode
            $command->prependCommand('set -e');
        } else {
            // turn off this mode because it may possibly be enabled by previous
            // commands
            $command->prependCommand('set +e');
        }
    }
}

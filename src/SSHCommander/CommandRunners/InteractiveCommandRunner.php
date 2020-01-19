<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandRunnerInterface;
use Neskodi\SSHCommander\Exceptions\CommandRunException;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\SSHCommand;
use Neskodi\SSHCommander\SSHConfig;

class InteractiveCommandRunner
    extends BaseCommandRunner
    implements SSHCommandRunnerInterface,
               DecoratedCommandRunnerInterface
{
    /**
     * @var array
     */
    protected $options = [];

    protected $endMarker = '';

    protected $errMarker = '';

    protected $initialWorkingDirectory = null;

    protected $errorTrapStatus = false;

    protected $isTrappedError = false;

    public function run(SSHCommandInterface $command): SSHCommandResultInterface
    {
        // Reset the environment before each run
        $this->reset();

        // append command end marker to the command so that SSHConnection
        // can stop reading when it detects either this marker or the error one
        // in the output
        $this->appendCommandEndMarkerEcho($command);

        return parent::run($command);
    }

    /**
     * Reset the environment before each run by creating unique end and error
     * markers, clearing the current working directory etc.
     */
    protected function reset()
    {
        // ensure a unique command end and error markers for each run
        $this->createEndMarker();
        $this->createErrMarker();

        $this->initialWorkingDirectory = null;
    }

    public function execDecorated(SSHCommandInterface $command): void
    {
        $this->enableMarkers();

        $this->getConnection()->execInteractive($command);

        $this->disableMarkers();
    }

    /**
     * Execute the command on the prepared connection.
     *
     * @param SSHCommandInterface $command
     */
    public function executeOnConnection(SSHCommandInterface $command): void
    {
        $this->getConnection()->execInteractive($command);
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

    /**
     * In the interactive mode, we read the exit code directly from the output.
     *
     * @param SSHCommandInterface $command
     *
     * @return int|null
     */
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

    protected function createEndMarker()
    {
        $this->endMarker = uniqid();
    }

    protected function createErrMarker()
    {
        $this->errMarker = uniqid();
    }

    protected function getEndMarker()
    {
        if (!$this->endMarker) {
            $this->createEndMarker();
        }

        return $this->endMarker;
    }

    protected function getErrMarker()
    {
        if (!$this->errMarker) {
            $this->createErrMarker();
        }

        return $this->errMarker;
    }

    protected function getEndMarkerEchoCommand()
    {
        return sprintf('echo "$?:%s"', $this->getEndMarker());
    }

    protected function getErrMarkerEchoCommand()
    {
        return sprintf('echo "$?:%s"', $this->getErrMarker());
    }

    protected function buildErrMarkerTrap()
    {
        $errMarkerEchoCommand = $this->getErrMarkerEchoCommand();

        return sprintf("trap '%s;exit' ERR", $errMarkerEchoCommand);
    }

    /**
     * Generate a unique command end marker so we can catch it in the shell
     * output and know when to continue. In this output, we'll also capture the
     * exit code of the last command in user's input.
     *
     * @param SSHCommandInterface $command
     */
    protected function appendCommandEndMarkerEcho(SSHCommandInterface $command)
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
        return sprintf(
            '/(\d+):(%s|%s)/',
            $this->getEndMarker(),
            $this->getErrMarker()
        );
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
        if (
            empty($this->endMarker) ||
            empty($this->errMarker) ||
            !count($outputLines)
        ) {
            // we won't be able to read the code
            return null;
        }

        $regex   = $this->getEndMarkerRegex();
        $matches = [];

        foreach ($outputLines as $i => $line) {
            preg_match_all($regex, $line, $matches);

            // TODO use named capturing groups instead of 1 and 2
            if (!empty($matches[0])) {
                // remove that line from the output
                unset($outputLines[$i]);

                // return the exit code
                return (int)$matches[1][0];
            }
        }

        // we were unable to read the exit code
        return null;
    }

    /**
     * Before the main command gets executed on the connection, run the
     * preliminary command to set up an error trap if user wants to break on
     * errors.
     *
     * @param SSHCommandInterface $command
     *
     * @throws CommandRunException
     */
    public function setupErrorHandler(SSHCommandInterface $command)
    {
        if (SSHConfig::BREAK_ON_ERROR_ALWAYS === $command->getConfig('break_on_error')) {
            $trap = $this->buildErrMarkerTrap();
            $this->executeIntermediateCommand($trap);
            $this->errorTrapStatus = true;
        }
    }

    /**
     * Remove the previously set error trap.
     * @throws CommandRunException
     */
    public function handleErrors(): void
    {
        if ($this->errorTrapStatus) {
            $this->executeIntermediateCommand('trap - ERR');
            $this->errorTrapStatus = false;
        }

        if ($this->isTrappedError) {
            $this->debug('Resetting the connection...');
            $this->getConnection()->getSSH2()->reset();
        }
    }

    /**
     * If command needs to be executed in a specific working directory, cd into
     * that directory before running the main command.
     *
     * @param SSHCommandInterface $command
     *
     * @throws CommandRunException
     */
    public function setupBasedir(SSHCommandInterface $command)
    {
        $basedir = $command->getConfig('basedir');

        if ($basedir && is_string($basedir)) {
            $this->grabInitialWorkingDirectory();

            $this->debug(sprintf('Setting working directory to "%s"', $basedir));
            $basedirCommand = sprintf('cd %s', escapeshellarg($basedir));

            $this->executeIntermediateCommand($basedirCommand);
        }
    }

    /**
     * Remember the current working directory where we were before running the
     * user command, to be able to restore it after running the main command
     *
     * @throws CommandRunException
     */
    protected function grabInitialWorkingDirectory()
    {
        try {
            $outputLines = $this->executeIntermediateCommand('pwd');
        } catch (CommandRunException $exception) {
            throw new CommandRunException('Unable to read current working directory');
        }

        $dir = $outputLines[0];

        $this->debug(sprintf(
            'Remembered working directory "%s" before running the command',
            $dir
        ));

        $this->initialWorkingDirectory = $dir;
    }

    /**
     * Restore any current working directory that was set before we changed to
     * the basedir for the main command.
     *
     * @throws CommandRunException
     */
    public function teardownBasedir()
    {
        if (!$this->initialWorkingDirectory) {
            // nothing to restore
            return;
        }

        try {
            $command = sprintf('cd %s', escapeshellarg($this->initialWorkingDirectory));
            $this->executeIntermediateCommand($command);
        } catch (CommandRunException $exception) {
            throw new CommandRunException(
                'Unable to restore working directory after running user command'
            );
        }

        $this->initialWorkingDirectory = null;
    }

    /**
     * @param SSHCommandInterface $command
     */
    public function handleTimeouts(SSHCommandInterface $command): void
    {
        if ($this->getConnection()->isTimeout()) {
            $this->executeTimeoutBehavior($command);
        } elseif ($this->getConnection()->isTimelimit()) {
            $this->executeTimelimitBehavior($command);
        }
    }

    protected function executeTimeoutBehavior(SSHCommandInterface $command): void
    {
        $behavior = $command->getConfig('timeout_behavior');

        if (!is_string($behavior)) {
            return;
        }

        $this->getConnection()->write($behavior);
    }

    protected function executeTimelimitBehavior(SSHCommandInterface $command): void
    {
        $behavior = $command->getConfig('timelimit_behavior');

        if (!is_string($behavior)) {
            return;
        }

        $this->getConnection()->write($behavior);
    }

    /**
     * Execute auxiliary commands before and after the main one
     *
     * @param string $command
     * @param array  $options
     *
     * @return array
     * @throws CommandRunException
     */
    protected function executeIntermediateCommand(
        string $command,
        array $options = []
    ): array {
        // by default, intermediate commands run with a strict time limit policy
        $defaultOptions = [
            'timeout'            => 1,
            'timelimit'          => 1,
            'timelimit_behavior' => SSHConfig::SIGNAL_TERMINATE,
        ];
        $options        = array_merge($defaultOptions, $options);

        $command        = new SSHCommand($command, $options);

        // intermediate commands run without marker
        $this->disableMarkers();

        $this->executeOnConnection($command);

        return $this->getStdOutLines($command);
    }

    protected function enableMarkers(): void
    {
        $this->getConnection()->setMarkerRegex($this->getEndMarkerRegex());
    }

    protected function disableMarkers(): void
    {
        $this->getConnection()->resetMarkers();
    }
}

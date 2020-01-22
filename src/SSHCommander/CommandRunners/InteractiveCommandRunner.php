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
    protected $endMarker = '';

    protected $errMarker = '';

    protected $outputLines = [];

    protected $exitCode = null;

    protected $detectedMarker = null;

    protected $initialWorkingDirectory = null;

    protected $errorTrapStatus = false;

    protected $errorWasTrapped = false;

    /**
     * Run the command in the interactive shell and return the result object.
     *
     * @param SSHCommandInterface $command
     *
     * @return SSHCommandResultInterface
     */
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
    protected function reset(): void
    {
        // ensure a unique command end and error markers for each run
        $this->createEndMarker();
        $this->createErrMarker();

        // reset flags and variables
        $this->initialWorkingDirectory = null;
        $this->outputLines = [];
        $this->exitCode = null;
        $this->detectedMarker = null;
        $this->errorWasTrapped = false;
        $this->errorTrapStatus = false;
    }

    /**
     * Execute command on connection, using end and error markers. This method
     * is used in the decorator chain. It should only be used for the main
     * command (the one user wants to run) and shouldn't be used for
     * auxiliary / intermediate commands pre/app-pended by the command runner
     * itself.
     *
     * @param SSHCommandInterface $command
     */
    public function execDecorated(SSHCommandInterface $command): void
    {
        $this->enableMarkers();

        $this->executeOnConnection($command);

        $this->disableMarkers();

        // Prepare the output before running decorators' closing methods.
        $this->analyzeOutput();
    }

    /**
     * Just execute the command on the connection in the interactive shell.
     *
     * @param SSHCommandInterface $command
     */
    public function executeOnConnection(SSHCommandInterface $command): void
    {
        $this->getConnection()->execInteractive($command);
    }

    /**
     * Execute auxiliary commands before and after the main one.
     *
     * @param string $command
     * @param array  $options
     *
     * @return array
     */
    protected function executeIntermediateCommand(
        string $command,
        array $options = []
    ): array {
        $defaultOptions = ['timeout' => 1, 'timelimit' => 1];
        $options        = array_merge($defaultOptions, $options);

        $command = new SSHCommand($command, $options);

        // intermediate commands run without marker
        $this->disableMarkers();

        $this->executeOnConnection($command);
        // if we hit timelimit, cancel the command
        if ($this->getConnection()->isTimeoutOrTimelimit()) {
            $this->getConnection()->terminateCommand();
        }

        return $this->getConnection()->getStdOutLines();
    }

    /**
     * Populate $this->outputLines array. Extract necessary information from the
     * command output, like exit code and detected marker. Remove the line with
     * the marker from the output, since user is not interested in it.
     *
     * @return void
     */
    protected function analyzeOutput(): void
    {
        $this->outputLines = $this->getConnection()->getStdOutLines();

        if (
            empty($this->endMarker) ||
            empty($this->errMarker) ||
            !count($this->outputLines)
        ) {
            return;
        }

        foreach ($this->outputLines as $i => $line) {
            if ($found = $this->readMarker($line)) {
                // remove this line from the output
                unset($this->outputLines[$i]);

                // set the exit code
                $this->exitCode = $found['code'];
                $this->detectedMarker = $found['marker'];

                // if it's a trapped error, set the flag
                if ($this->getErrMarker() === $found['marker']) {
                    $this->errorWasTrapped = true;
                }

                break;
            }
        }
    }

    /**
     * Read a line of output and see if it contains a marker with an exit code,
     * if so, return them.
     *
     * @param string $line
     *
     * @return null|array
     */
    protected function readMarker(string $line): ?array
    {
        $regex   = $this->getEndMarkerRegex();
        $matches = [];

        preg_match_all($regex, $line, $matches);

        if (!empty($matches[0])) {
            return [
                'code'   => $matches['CODE'][0],
                'marker' => $matches['MARKER'][0],
            ];
        }

        return null;
    }

    /**
     * In the interactive mode, we read the exit code directly from the output.
     * This processing happens in the analyzeOutput method.
     *
     * @param SSHCommandInterface $command
     *
     * @return int|null
     */
    public function getLastExitCode(SSHCommandInterface $command): ?int
    {
        return $this->exitCode;
    }

    /**
     * Get the output lines produced by the command, after any processing
     * has been done by the analyzeOutput method.
     *
     *
     * @param SSHCommandInterface $command
     *
     * @return array
     */
    public function getStdOutLines(SSHCommandInterface $command): array
    {
        return $this->outputLines;
    }

    /**
     * Get the error lines produced by the command. Not possible in the
     * interactive shell (stderr and stdout are both connected to the terminal).
     *
     * @param SSHCommandInterface $command
     *
     * @return array
     */
    public function getStdErrLines(SSHCommandInterface $command): array
    {
        // The interactive runner doesn't enjoy the luxury of a separate error
        // stream
        return [];
    }

    /**
     * Generate a unique sequence of characters that will be echoed by the shell
     * after running the main command. We will watch the output for this sequence
     * and understand when the command finished running.
     */
    protected function createEndMarker(): void
    {
        $this->endMarker = uniqid();
    }

    /**
     * Generate a unique sequence of characters that will be echoed by the shell
     * when an error is trapped. We will watch the output for this sequence and
     * and understand when something went wrong.
     */
    protected function createErrMarker(): void
    {
        $this->errMarker = uniqid();
    }

    /**
     * Get the currently used end marker. If none was created yet, create one
     * now.
     *
     * @return string
     */
    protected function getEndMarker(): string
    {
        if (!$this->endMarker) {
            $this->createEndMarker();
        }

        return $this->endMarker;
    }

    /**
     * Get the currently used error marker. If none was created yet, create one
     * now.
     *
     * @return string
     */
    protected function getErrMarker(): string
    {
        if (!$this->errMarker) {
            $this->createErrMarker();
        }

        return $this->errMarker;
    }

    /**
     * Enable the SSHConnection to look for markers in the command output by
     * telling it which regular expression to use. SSHConnection will stop
     * reading when it detects any of the markers.
     *
     * @return void
     */
    protected function enableMarkers(): void
    {
        $this->getConnection()->setMarkerRegex($this->getEndMarkerRegex());
    }

    /**
     * Tell the SSHConnection not to look for any markers in the output. It will
     * rely on reading the command prompt, using the 'prompt_regex' config value.
     *
     * @return void
     */
    protected function disableMarkers(): void
    {
        $this->getConnection()->resetMarkers();
    }

    /**
     * Get the echo command that will be appended at the end of user's command
     * and will echo the 'end marker'. SSHConnection will expect to see this
     * marker in the output to understand that user's command has finished
     * running, and stop reading further. In this output, we'll also capture the
     * exit code of the last command in user's input, to compensate for inability
     * to get exit codes from phpseclib during an interactive shell session.
     *
     * @return string
     */
    protected function getEndMarkerEchoCommand(): string
    {
        return sprintf('echo "$?:%s"', $this->getEndMarker());
    }

    /**
     * Get the echo command that will be appended to the error trap. When the
     * shell traps an error, it will echo this marker. SSHConnection will watch
     * for this marker in the output to understand that an error happened while
     * running user's command, and stop reading further. In this output, we'll
     * also capture the exit code of the last command in user's input.
     *
     * @return string
     */
    protected function getErrMarkerEchoCommand(): string
    {
        return sprintf('echo "$?:%s"', $this->getErrMarker());
    }

    /**
     * Build the command that will set an error trap. This trap will be used
     * when 'break_on_error' is true (i.e. BREAK_ON_ERROR_ALWAYS) and we need
     * to catch errors that happen in the middle of user-provided sequence of
     * commands.
     *
     * @return string
     */
    protected function buildErrMarkerTrap(): string
    {
        $errMarkerEchoCommand = $this->getErrMarkerEchoCommand();

        return sprintf("trap '%s;exit' ERR", $errMarkerEchoCommand);
    }

    /**
     * Append the command echoing the end marker to the end of user's command.
     *
     * @param SSHCommandInterface $command
     */
    protected function appendCommandEndMarkerEcho(
        SSHCommandInterface $command
    ): void {
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
            '/(?<CODE>\d+):(?<MARKER>%s|%s)/',
            $this->getEndMarker(),
            $this->getErrMarker()
        );
    }

    /**
     * Before the main command gets executed on the connection, run the
     * preliminary command to set up an error trap if user wants to break on
     * errors.
     *
     * This function is called by CRErrorHandlerDecorator.
     *
     * @param SSHCommandInterface $command
     *
     * @noinspection PhpUnused
     */
    public function setupErrorHandler(SSHCommandInterface $command): void
    {
        if (SSHConfig::BREAK_ON_ERROR_ALWAYS === $command->getConfig('break_on_error')) {
            $trap = $this->buildErrMarkerTrap();
            $this->executeIntermediateCommand($trap);
            $this->errorTrapStatus = true;
        }
    }

    /**
     * Remove the previously set error trap.
     *
     * This function is called by CRErrorHandlerDecorator.
     *
     * @noinspection PhpUnused
     */
    public function handleErrors(): void
    {
        if ($this->errorTrapStatus) {
            $this->executeIntermediateCommand('trap - ERR');
            $this->errorTrapStatus = false;
        }
    }

    /**
     * If user's command needs to be executed in a specific working directory,
     * cd into that directory before running the main command.
     *
     * This method is called by CRBasedirDecorator.
     *
     * @param SSHCommandInterface $command
     *
     * @throws CommandRunException
     *
     * @noinspection PhpUnused
     */
    public function setupBasedir(SSHCommandInterface $command): void
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
     *
     * @noinspection PhpRedundantCatchClauseInspection
     */
    protected function grabInitialWorkingDirectory(): void
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
     * This method is called by CRBasedirDecorator.
     *
     * @throws CommandRunException
     *
     * @noinspection PhpUnused
     * @noinspection PhpRedundantCatchClauseInspection
     */
    public function teardownBasedir(): void
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
     * Handle the timeout and timelimit situations by executing whatever
     * behavior user has configured for these cases. The most common example is
     * to send CTRL+C to cancel command execution, which is achieved by setting
     * 'timelimit_behavior' => SSHConfig::SIGNAL_TERMINATE in the command config.
     *
     * @param SSHCommandInterface $command
     *
     * @noinspection PhpUnused
     */
    public function handleTimeouts(SSHCommandInterface $command): void
    {
        if ($this->getConnection()->isTimeout()) {
            $this->executeTimeoutBehavior($command);
        } elseif ($this->getConnection()->isTimelimit()) {
            $this->executeTimelimitBehavior($command);
        }
    }

    /**
     * Execute the behavior user has set up for the timeout situations (i.e. for
     * situations when SSH connection is waiting for command output longer than
     * allowed). User can define this behavior by setting e.g.
     * 'timeout_behavior' => SSHConfig::SIGNAL_TERMINATE in the command config.
     *
     * By default, no action is taken.
     *
     * @param SSHCommandInterface $command
     */
    protected function executeTimeoutBehavior(SSHCommandInterface $command): void
    {
        $behavior = $command->getConfig('timeout_behavior');

        if (!is_string($behavior)) {
            return;
        }

        $this->getConnection()->write($behavior);
    }

    /**
     * Execute the behavior user has set up for the timeout situations (i.e. for
     * situations when SSH command is running longer than allowed, regardless
     * of whether it produces any output). User can define this behavior by setting e.g.
     * 'timelimit_behavior' => SSHConfig::SIGNAL_TERMINATE in the command config.
     *
     * By default, no action is taken.
     *
     * @param SSHCommandInterface $command
     */
    protected function executeTimelimitBehavior(SSHCommandInterface $command): void
    {
        $behavior = $command->getConfig('timelimit_behavior');

        if (!is_string($behavior)) {
            return;
        }

        $this->getConnection()->write($behavior);
    }

    /**
     * After running the main command, we need to see if an error was trapped.
     * If it is so, it means that the SSH connection has been reset and the
     * channel contains an extra output we'll need to clean up.
     *
     * This function is called by the CRCleanupDecorator in the decorator chain.
     *
     * @noinspection PhpUnused
     */
    public function cleanupAfterCommand(): void
    {
        if ($this->errorWasTrapped) {
            $this->getConnection()->cleanCommandBuffer();
        }
    }
}

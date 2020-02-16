<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\CommandRunners\Decorators\CRMarkerDetectionDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRPromptDetectionDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRTimeoutHandlerDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRErrorHandlerDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRConnectionDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRBasedirDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRCleanupDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRLoggerDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRResultDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRTimerDecorator;
use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandRunnerInterface;
use Neskodi\SSHCommander\Exceptions\CommandRunException;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Traits\UsesMarkers;
use Neskodi\SSHCommander\SSHCommand;
use Neskodi\SSHCommander\SSHConfig;

class InteractiveCommandRunner
    extends BaseCommandRunner
    implements SSHCommandRunnerInterface,
               DecoratedCommandRunnerInterface
{
    use UsesMarkers;

    protected $outputLines = [];

    protected $exitCode;

    protected $detectedMarker;

    protected $detectedMarkerType;

    protected $initialWorkingDirectory;

    /** @var bool */
    protected $errorTrapIsEnabled = false;

    /**@var bool */
    protected $errorWasTrapped = false;

    public function withDecorators(): DecoratedCommandRunnerInterface
    {
        // Add command decorators
        // !! ORDER MATTERS !!
        return $this->with(CRTimerDecorator::class)
                    ->with(CRLoggerDecorator::class)
                    ->with(CRResultDecorator::class)
                    ->with(CRBasedirDecorator::class)
                    ->with(CRErrorHandlerDecorator::class)
                    ->with(CRTimeoutHandlerDecorator::class)
                    ->with(CRPromptDetectionDecorator::class)
                    ->with(CRMarkerDetectionDecorator::class)
                    ->with(CRCleanupDecorator::class)
                    ->with(CRConnectionDecorator::class);
    }

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

        return parent::run($command);
    }

    /**
     * Reset the variables before each run.
     */
    protected function reset(): void
    {
        // reset flags and variables
        $this->initialWorkingDirectory = null;
        $this->outputLines             = [];
        $this->exitCode                = null;
        $this->detectedMarker          = null;
        $this->detectedMarkerType      = null;
        $this->errorWasTrapped         = false;
        $this->errorTrapIsEnabled      = false;
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
        $this->executeOnConnection($command);

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
        $command = new SSHCommand($command, $options);
        $command->stopsOnPrompt();

        $this->getConnection()->setTimeout(1);

        $this->executeOnConnection($command);

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
            empty($this->markers) ||
            !count($this->outputLines)
        ) {
            return;
        }

        foreach ($this->outputLines as $i => $line) {
            if ($found = $this->readMarker($line)) {
                // remove this line from the output
                unset($this->outputLines[$i]);

                // set the exit code
                $this->exitCode           = $found['code'];
                $this->detectedMarker     = $found['marker'];
                $this->detectedMarkerType = $found['type'];

                // if it's a trapped error, set the flag
                if (CRMarkerDetectionDecorator::MARKER_TYPE_ERROR === $found['type']) {
                    $this->errorWasTrapped = true;
                }

                break;
            }
        }
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
     * ERROR HANDLING LOGIC SPECIFIC TO THE INTERACTIVE COMMAND RUNNER
     */

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
            $this->errorTrapIsEnabled = true;
        }
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
        $errMarkerEchoCommand = $this->getMarkerEchoCommand(
            CRMarkerDetectionDecorator::MARKER_TYPE_ERROR
        );

        return sprintf("trap '%s;exit' ERR", $errMarkerEchoCommand);
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
        if ($this->errorTrapIsEnabled) {
            $this->executeIntermediateCommand('trap - ERR');
            $this->errorTrapIsEnabled = false;
        }
    }

    /**
     * BASEDIR HANDLING LOGIC SPECIFIC TO THE INTERACTIVE COMMAND RUNNER
     */

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
     * CLEANUP LOGIC SPECIFIC TO THE INTERACTIVE COMMAND RUNNER
     */

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

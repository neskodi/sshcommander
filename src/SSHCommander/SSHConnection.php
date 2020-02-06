<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Traits\SSHConnection\AuthenticatesSSH2;
use Neskodi\SSHCommander\Traits\SSHConnection\InteractsWithSSH2;
use Neskodi\SSHCommander\Traits\SSHConnection\ConfiguresSSH2;
use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Interfaces\ConfigAwareInterface;
use Neskodi\SSHCommander\Interfaces\LoggerAwareInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Interfaces\TimerInterface;
use Neskodi\SSHCommander\Traits\ConfigAware;
use Neskodi\SSHCommander\Dependencies\SSH2;
use Neskodi\SSHCommander\Traits\Loggable;
use Neskodi\SSHCommander\Traits\Timer;
use Psr\Log\LoggerInterface;

class SSHConnection implements
    SSHConnectionInterface,
    LoggerAwareInterface,
    ConfigAwareInterface,
    TimerInterface
{
    use Loggable, Timer, ConfigAware;
    use AuthenticatesSSH2, ConfiguresSSH2, InteractsWithSSH2;

    /**
     * @var SSH2
     */
    protected $ssh2;

    /**
     * @var array
     */
    protected $stdoutLines = [];

    /**
     * @var array
     */
    protected $stderrLines = [];

    /**
     * @var int
     */
    protected $lastExitCode;

    /**
     * @var SSHCommandInterface
     */
    protected $command;

    /**
     * @var bool
     */
    protected $isTimeout = false;

    /**
     * @var bool
     */
    protected $isTimelimit = false;

    /**
     * @var string|null
     */
    protected $markerRegex = null;

    /**
     * SSHConnection constructor.
     *
     * @param SSHConfigInterface   $config
     * @param LoggerInterface|null $logger
     *
     * @throws AuthenticationException
     */
    public function __construct(
        SSHConfigInterface $config,
        ?LoggerInterface $logger = null
    ) {
        $this->setConfig($config);

        if ($logger) {
            $this->setLogger($logger);
        }

        if ($this->getConfig('autologin')) {
            $this->authenticate();
        }
    }

    /**
     * Proxy any unknown method directly to the SSH2 object.
     *
     * @param $name
     * @param $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->getSSH2(), $name], $arguments);
    }

    /**
     * Check if we have all necessary information to establish and authenticate
     * a connection.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        $config = $this->getConfig();

        return $config instanceof SSHConfigInterface &&
               $config->isValid();
    }

    /**
     * Get the phpseclib SSH2 object. Create one if needed.
     * The credentials are taken from $this->config.
     *
     * @return SSH2
     */
    public function getSSH2(): SSH2
    {
        if (!$this->ssh2) {
            [$host, $port] = [
                $this->getConfig()->getHost(),
                $this->getConfig()->getPort(),
            ];

            $this->ssh2 = new SSH2($host, $port);
        }

        return $this->ssh2;
    }

    /**
     * Read the channel packets and build the output. Stop when we detect a
     * marker or a prompt.
     *
     * @return string
     */
    public function read(): string
    {
        $output = '';

        $this->startTimer();

        while ($str = $this->sshRead('', SSH2::READ_NEXT)) {
            if (is_bool($str)) {
                break;
            }

            $output .= $str;

            if ($this->exceedsTimeLimit()) {
                $this->isTimelimit = true;
                break;
            }

            if ($this->usesMarkers() && $this->hasMarker($output)) {
                break;
            }

            if (!$this->usesMarkers() && $this->hasPrompt($output)) {
                break;
            }
        }

        $this->stopTimer();

        $this->debug('READ: ' . Utils::oneLine($output));

        return $output;
    }

    /**
     * Write characters to SSH PTY (interactive shell).
     *
     * Characters will be written exactly as provided, nothing is added or
     * altered. If you don't provide the "line feed", for example, command
     * will sit there on the command line but won't be submitted.
     *
     * @param string $chars
     *
     * @return bool
     */
    public function write(string $chars)
    {
        $this->debug('WRITE: ' . Utils::oneLine($chars));

        return $this->sshWrite($chars);
    }

    /**
     * Execute the command on the SSH connection, using phpseclib's exec()
     * method. Populates the stdout, stderr, and exit code variables.
     *
     * @param SSHCommandInterface $command
     */
    public function exec(SSHCommandInterface $command): void
    {
        $ssh = $this->getSSH2();

        $this->sshExec((string)$command, function ($str) use ($command) {
            // collect the stdout stream
            $this->stdoutLines = array_merge(
                $this->stdoutLines,
                $this->processOutput($command, $str)
            );
        });

        // don't forget to collect the error stream too
        if ($command->getConfig('separate_stderr')) {
            $this->stderrLines = $this->processOutput($command, $ssh->getStdError());
        }

        $this->lastExitCode = $ssh->getExitStatus();
    }

    /**
     * Write a sequence of characters into the command line and submit them for
     * execution by appending a line feed "\n" at the end (if not already there)
     *
     * @param string $chars
     *
     * @return bool
     */
    public function writeAndSend(string $chars)
    {
        if ("\n" !== substr($chars, -1)) {
            $chars .= "\n";
        }

        return $this->write($chars);
    }

    /**
     * Send the terminate signal (CTRL+C) to the shell.
     */
    public function terminateCommand(): void
    {
        $this->write(SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE);
    }

    /**
     * Send the 'suspend in background' signal (CTRL+Z) to the shell.
     */
    public function suspendCommand(): void
    {
        $this->write(SSHConfig::TIMEOUT_BEHAVIOR_SUSPEND);
    }

    /**
     * Set the currently used config from the provided object.
     *
     * @param SSHConfigInterface $config
     *
     * @return $this
     */
    public function configureForCommand(SSHConfigInterface $config): SSHConnectionInterface
    {
        $this->setConfig($config);

        $this->ssh2->setLogger($this->getLogger());

        $this->ssh2->configureTimeouts(null, function () {
            $isTimeout = $this->exceedsTimeLimit();
            if ($isTimeout) {
                $this->terminateCommand();
            }

            return $isTimeout;
        });

        return $this;
    }

    /**
     * Execute a command or a series of commands. Proxy to phpseclib SSH2
     * exec() method.
     *
     * @param SSHCommandInterface $command the command to execute, or multiple
     *                                     commands separated by newline.
     *
     * @return SSHConnectionInterface
     */
    public function execIsolated(SSHCommandInterface $command): SSHConnectionInterface
    {
        $this->configureForCommand($command->getConfig());

        $this->authenticateIfNecessary();

        $this->resetResults();

        $this->exec($command);

        return $this;
    }

    /**
     * Write command and read the output until we have a prompt, a marker
     * (end or error) or a timeout.
     *
     * Clean out the command itself and the prompt from the output and return it.
     *
     * @param SSHCommandInterface $command
     *
     * @return $this
     */
    public function execInteractive(SSHCommandInterface $command): SSHConnectionInterface
    {
        $this->configureForCommand($command->getConfig());

        $this->authenticateIfNecessary();

        $this->resetResults();

        $this->writeAndSend((string)$command);

        $output = $this->read();
        $output = $this->cleanCommandOutput($output, $command);

        $this->stdoutLines = $this->processOutput($command, $output);

        return $this;
    }

    /**
     * Before running the command, we'll do an additional 'read' operation to
     * remove any junk that may be hanging there in the channel left by the
     * previous commands.
     */
    public function cleanCommandBuffer(): void
    {
        $this->debug('Cleaning buffer...');

        $ssh = $this->getSSH2();

        $ssh->setTimeout(1);
        $output = '';

        while ($str = $this->sshRead('', SSH2::READ_NEXT)) {
            if (is_bool($str)) {
                break;
            }

            $output .= $str;
            if ($this->hasPrompt($output)) {
                break;
            }
        }

        $ssh->setTimeout($this->getConfig('timeout'));

        $this->debug('JUNK: ' . Utils::oneLine($output));
        $this->debug('End cleaning buffer');
    }

    /**
     * SSH2::read() returns the entire interactive buffer, including the command
     * itself and the command prompt in the end. We are only interested in the
     * command output, so we will strip off these artifacts.
     *
     * @param string              $output
     * @param SSHCommandInterface $command
     *
     * @return false|string|string[]|null
     */
    protected function cleanCommandOutput(string $output, SSHCommandInterface $command): string
    {
        $firstCommandRegex = '/^.*?(\r\n|\r|\n)/';
        $promptRegex       = $command->getConfig()->getPromptRegex();

        // carefully inject command after prompt into prompt regex
        $promptRegexWithCommand = preg_replace(
            '/^(.)(.+?)(\\$)?\\1([a-z]*)$/',
            '\1\2.*(\r\n|\r|\n)\1\4',
            $promptRegex
        );

        // clean out the first subcommand from the beginning
        $output = preg_replace($firstCommandRegex, '', $output);

        // clean out all subsequent prompts with following commands
        $output = preg_replace($promptRegexWithCommand, '', $output);

        // clean out the command prompt from the end
        $output = preg_replace($promptRegex, '', $output);

        $this->debug('CLEAN: ' . Utils::oneLine($output));

        return $output;
    }

    /**
     * If user has set the timeout condition to be 'runtime' and the command is
     * already running longer than specified by the 'timeout' config value,
     * return true.
     *
     * @return bool
     */
    protected function exceedsTimeLimit(): bool
    {
        // if user didn't set the timeout condition to 'runtime', no action
        // is necessary either
        if (SSHConfig::TIMEOUT_CONDITION_RUNTIME !== $this->getConfig('timeout_condition')) {
            return false;
        }

        // If user has set the timeout to 0 or a falsy value, no action
        // is necessary either
        if (!$timeout = $this->getConfig('timeout')) {
            // user does not want any timeout
            return false;
        }

        return microtime(true) > ($this->getTimerStart() + $timeout);
    }

    /**
     * See if current received output from the command contains a command
     * prompt, as defined by the config value 'prompt_regex'.
     *
     * @param string $output
     *
     * @return bool
     */
    protected function hasPrompt(string $output): bool
    {
        $regex = $this->getConfig()->getPromptRegex();

        return $this->hasExpectedOutputRegex($output, $regex);
    }

    /**
     * See if current received output from the command contains the specified
     * marker, which is matched via a regular expression, like prompt.
     *
     * @param string $output
     *
     * @return bool
     */
    protected function hasMarker(string $output): bool
    {
        return $this->hasExpectedOutputRegex($output, $this->markerRegex);
    }

    /**
     * Set the regular expression used to detect 'end' and 'error' markers in
     * the output.
     *
     * @param string $regex
     *
     * @return SSHConnectionInterface
     */
    public function setMarkerRegex(string $regex): SSHConnectionInterface
    {
        $this->markerRegex = $regex;

        return $this;
    }

    /**
     * Tell the connection not to look for any markers and just rely on the
     * prompt.
     *
     * @return $this
     */
    public function resetMarkers(): SSHConnectionInterface
    {
        $this->markerRegex = null;

        return $this;
    }

    /**
     * See if we are told to look for markers in the output.
     *
     * @return bool
     */
    protected function usesMarkers(): bool
    {
        return (bool)$this->markerRegex;
    }

    /**
     * Check if current received output from the command already contains a
     * substring expected by user.
     *
     * @param string $output
     * @param string $expect
     *
     * @return bool
     *
     * @noinspection PhpUnused
     */
    protected function hasExpectedOutputSimple(string $output, string $expect)
    {
        $strPosFunction = function_exists('mb_strpos') ? 'mb_strpos' : 'strpos';

        return false !== $strPosFunction($output, $expect);
    }

    /**
     * Check if current received output matches the regular expression expected
     * by user.
     *
     * @param string $output
     * @param string $expect
     *
     * @return false|int
     */
    protected function hasExpectedOutputRegex(string $output, string $expect)
    {
        return preg_match($expect, $output);
    }

    /**
     * Process an intermediate sequence of output characters before merging it
     * with the main array of output lines.
     *
     * @param SSHCommandInterface $command used to look up configuration
     * @param string              $chars
     *
     * @return array
     */
    protected function processOutput(SSHCommandInterface $command, string $chars): array
    {
        return $this->splitOutput($command, $chars);
    }

    /**
     * Split output lines by a regular expression or simple character(s),
     * according to command configuration.
     *
     * @param SSHCommandInterface $command
     * @param string              $chars
     *
     * @return array
     */
    protected function splitOutput(SSHCommandInterface $command, string $chars): array
    {
        // see if user wants to split by regular expression
        if ($delim = $command->getConfig('delimiter_split_output_regex')) {
            return preg_split($delim, $chars) ?: [];
        }

        // see if user wants to explode by a simple delimiter
        if ($delim = $command->getConfig('delimiter_split_output')) {
            return explode($delim, $chars) ?: [];
        }

        // otherwise no splitting can be performed
        return [$chars];
    }

    /**
     * Get the array of output lines returned by last command.
     *
     * @return array
     */
    public function getStdOutLines(): array
    {
        return $this->stdoutLines;
    }

    /**
     * Get the array of error lines returned by last command.
     *
     * @return array
     */
    public function getStdErrLines(): array
    {
        return $this->stderrLines;
    }

    /**
     * Get the exit code of the last command, if any.
     *
     * @return int|null
     */
    public function getLastExitCode(): ?int
    {
        if (is_null($this->lastExitCode)) {
            return null;
        }

        return (int)$this->lastExitCode;
    }

    /**
     * Clear all accumulated results of previous command runs.
     *
     * @return $this
     */
    public function resetResults(): SSHConnectionInterface
    {
        $this->resetOutput();
        $this->resetTimeoutStatus();

        return $this;
    }

    /**
     * Clear all traces of previous commands.
     *
     * @return $this
     */
    protected function resetOutput(): SSHConnectionInterface
    {
        $this->stdoutLines  = $this->stderrLines = [];
        $this->lastExitCode = null;

        return $this;
    }

    /**
     * Clear the timeout status of possible previous commands.
     *
     * @return $this
     */
    protected function resetTimeoutStatus(): SSHConnectionInterface
    {
        $this->isTimeout   = false;
        $this->isTimelimit = false;

        return $this;
    }

    /**
     * Check if 'noout' timeout condition has been reached while running this
     * command.
     *
     * @return bool
     */
    public function isTimeout(): bool
    {
        return (bool)$this->getSSH2()->isTimeout();
    }

    /**
     * Check if the 'runtime' timeout condition has been reached while running
     * this command.
     *
     * @return bool
     */
    public function isTimelimit(): bool
    {
        return $this->isTimelimit;
    }

    /**
     * Check if either 'runtime' or 'noout' timeout condition has been reached
     * while running this command.
     *
     * @return bool
     */
    public function isTimeoutOrTimelimit(): bool
    {
        return $this->isTimeout() || $this->isTimelimit();
    }
}

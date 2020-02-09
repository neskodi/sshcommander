<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Traits\SSHConnection\AuthenticatesSSH2;
use Neskodi\SSHCommander\Traits\SSHConnection\InteractsWithSSH2;
use Neskodi\SSHCommander\Traits\SSHConnection\ConfiguresSSH2;
use Neskodi\SSHCommander\VendorOverrides\phpseclib\Net\SSH2;
use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Interfaces\ConfigAwareInterface;
use Neskodi\SSHCommander\Interfaces\LoggerAwareInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Traits\HasOutputProcessor;
use Neskodi\SSHCommander\Interfaces\TimerInterface;
use Neskodi\SSHCommander\Traits\ConfigAware;
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
    use HasOutputProcessor;

    /**
     * @var SSH2
     */
    protected $ssh2;

    /** @var array */
    protected $stdoutLines = [];

    /** @var array */
    protected $stderrLines = [];

    /** @var int */
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

        // the default output processor, mainly for the authentication stage
        $this->setOutputProcessor(new SSHOutputProcessor($config));

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
            if ($this->logger) {
                $this->ssh2->setLogger($this->getLogger());
            }
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
            if (is_string($str)) {
                $this->output->add($str);
            }

            if ($this->reachedTimeLimit()) {
                break;
            }

            if (
                $this->usesMarkers() &&
                $this->output->hasMarker($this->markerRegex)
            ) {
                break;
            }

            if (
                !$this->usesMarkers() &&
                $this->output->hasPrompt()) {
                break;
            }

            if (is_bool($str)) {
                break;
            }
        }

        $this->stopTimer();

        $this->debug('READ: ' . Utils::oneLine($this->output->getRaw()));

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

        $this->debug('EXEC: ' . Utils::oneLine((string)$command));

        $this->sshExec((string)$command, function ($str) use ($command) {
            // collect the stdout stream
            $this->output->add($str);
        });

        // don't forget to collect the error stream too
        if ($command->getConfig('separate_stderr')) {
            $this->output->addErr($this->getSSH2()->getStdError());
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
     * Set the currently used config from the provided command object.
     *
     * @param SSHCommandInterface $command
     *
     * @return $this
     */
    public function configureForCommand(SSHCommandInterface $command): SSHConnectionInterface
    {
        $config = $command->getConfig();

        $this->setConfig($config);
        $this->setOutputProcessor(new SSHOutputProcessor($config));

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
        $this->configureForCommand($command);
        $this->authenticateIfNecessary();
        $this->resetResults();

        $this->exec($command);

        // get back the output (and error) as separate lines
        // (without cleaning it)
        $this->collectOutput(false);

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
        $this->configureForCommand($command);
        $this->authenticateIfNecessary();
        $this->resetResults();
        $this->writeAndSend((string)$command);

        $output = $this->read();
        $this->output->add($output);

        // get back the output split into separate lines
        $this->collectOutput();

        return $this;
    }

    /**
     * Do an additional 'read' operation to remove any junk that may be hanging
     * there in the channel left by the previous commands.
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
            if ($this->output->hasPrompt($output)) {
                break;
            }
        }

        $ssh->setTimeout($this->getConfig('timeout'));

        $this->debug('JUNK: ' . Utils::oneLine($output));
        $this->debug('End cleaning buffer');
    }

    /**
     * If user has set the timeout condition to be 'runtime' and the command is
     * already running longer than specified by the 'timeout' config value,
     * return true.
     *
     * @return bool
     */
    public function reachedTimeLimit(): bool
    {
        $timeout   = $this->getConfig('timeout');
        $condition = $this->getConfig('timeout_condition');
        $timeSinceCommandStart = $this->timeSinceCommandStart();

        $result = (
            // user wants to timeout by 'runtime'
            (SSHConfig::TIMEOUT_CONDITION_RUNTIME === $condition) &&
            // user has set a non-zero timeout value
            $timeout &&
            // and this time has passed since the command started
            ($timeSinceCommandStart >= $timeout)
        );

        if ($result) {
            $this->isTimelimit = true;
        }

        return $result;
    }

    /**
     * If user has set the timeout condition to be 'noout' and we have been waiting
     * for output  already longer than specified by the 'timeout' config value,
     * return true.
     *
     * @return bool
     */
    public function reachedTimeout(): bool
    {
        $timeout             = $this->getConfig('timeout');
        $condition           = $this->getConfig('timeout_condition');
        $timeSinceLastPacket = $this->timeSinceLastPacket();

        return (
            // user wants to timeout by 'noout'
            (SSHConfig::TIMEOUT_CONDITION_NOOUT === $condition) &&
            // user has set a non-zero timeout value
            $timeout &&
            // and this time has passed since the last packet was received
            ($timeSinceLastPacket >= $timeout)
        );
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
     * Check if either 'noout' or 'runtime' timeout condition has been reached
     * while running this command.
     *
     * @return bool
     */
    public function isTimeout(): bool
    {
        return (bool)$this->getSSH2()->isTimeout();
    }

    /**
     * Check if the 'runtime' timeout condition has specifically been reached
     * while running this command.
     *
     * @return bool
     */
    public function isTimelimit(): bool
    {
        return $this->isTimelimit;
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
     * Get the processed (and possibly cleaned) output back from the
     * output processor.
     *
     * @param bool $clean whether to tell the output processor to clean
     *                    the output. read/write needs this, while exec doesn't.
     */
    protected function collectOutput(bool $clean = true): void
    {
        $this->stdoutLines = $this->output->get($clean);
        $this->stderrLines = $this->output->getErr($clean);
    }

    /**
     * Return the time since last packet was received
     *
     * @return float
     */
    protected function timeSinceLastPacket(): float
    {
        $lastPacketTime = $this->getSSH2()->getLastPacketTime();

        // if no packet was yet received, we count from the time when command
        // started running
        if (is_null($lastPacketTime)) {
            return $this->timeSinceCommandStart();
        }

        // return the actual time since last packet
        return microtime(true) - $lastPacketTime;
    }

    /**
     * Return the time since the current command started running.
     *
     * @return float
     */
    protected function timeSinceCommandStart(): float
    {
        return microtime(true) - $this->getTimerStart();
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
}

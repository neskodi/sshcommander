<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Traits\SSHConnection\ControlsCommandFlow;
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
    use AuthenticatesSSH2, ConfiguresSSH2, InteractsWithSSH2, ControlsCommandFlow;
    use HasOutputProcessor;

    /** @var SSH2 */
    protected $ssh2;

    /** @var array */
    protected $stdoutLines = [];

    /** @var array */
    protected $stderrLines = [];

    /** @var int */
    protected $lastExitCode;

    /** @var SSHCommandInterface */
    protected $command;

    /** @var bool */
    protected $isTimeout = false;

    /** @var bool */
    protected $isTimelimit = false;

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
            $this->ssh2->setConnection($this);
            if ($this->logger) {
                $this->ssh2->setLogger($this->getLogger());
            }
        }

        return $this->ssh2;
    }

    /**
     * Set the currently used config from the provided command object.
     *
     * @param SSHCommandInterface $command
     *
     * @return $this
     */
    public function setCommand(SSHCommandInterface $command): SSHConnectionInterface
    {
        $this->command = $command;
        $config = $command->getConfig();

        $this->setConfig($config);
        $this->setOutputProcessor(new SSHOutputProcessor($config));

        return $this;
    }

    /**
     * Read the channel packets and build the output. Stop when we detect a
     * marker or a prompt.
     *
     * @return string
     */
    public function read(): string
    {
        // processPartialOutput() will run read cycle hooks and return true
        // if one of them tells us to stop reading.
        while ($result = $this->sshRead('', SSH2::READ_NEXT)) {
            if (
                $this->processPartialOutput($result) ||
                $this->getSSH2()->isTimeout()
            ) {
                break;
            }
        }

        $this->debug('READ: ' . Utils::oneLine($this->output->getRaw()));

        return $this->output->getAsString();
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
     *
     * @noinspection PhpInconsistentReturnPointsInspection
     */
    public function exec(SSHCommandInterface $command): void
    {
        $ssh = $this->getSSH2();

        $this->debug('EXEC: ' . Utils::oneLine((string)$command));

        $this->sshExec((string)$command, function ($str) {
            if ($this->processPartialOutput($str)) {
                return true;
            }
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
     * This SSHCommand object registers a number of hook functions to be
     * executed after each stream_select iteration (by default this happens
     * every 0.5 seconds while connection is waiting for output. If the command
     * produces output earlier, the hooks will be executed immediately upon output.
     *
     * CRTimeoutDecorator and other decorators register their own logic as hooks.
     * You may also register your own watcher to run per iteration, by calling
     * addReadIterationHook(). Your hook, when called, will receive $connection
     * (this object) and $stepOutput (new output that became available on the
     * channel since last reading, if any, an empty string otherwise) as arguments.
     * If you return a truthy value, the read cycle will stop and control
     * will be returned to your program.
     *
     * Please note that if you break the normal flow of command run,
     * it's your responsibility to stop the command e.g. by calling
     * $connection->terminateCommand(), and clean up channel artifacts via e.g.
     * $connection->getSSH2()->read() in your hook function.
     *
     * @param string $stepOutput the new (portion of) output returned by
     *                           the command on this step
     *
     * @return bool
     */
    public function runReadCycleHooks(string $stepOutput = ''): bool
    {
        if (!$this->command instanceof SSHCommandInterface) {
            // we don't have any hooks to run
            return false;
        }

        $shouldBreak = false;

        foreach ($this->command->getReadCycleHooks() as $hook) {
            if ($hook($this, $stepOutput)) {
                $shouldBreak = true;
            }
        }

        return $shouldBreak;
    }

    /**
     * Ingest the output of the current read iteration and analyze it.
     * Return true to stop command execution, false otherwise.
     *
     * The breaking conditions are:
     * - one of the read cycle hooks returned true;
     * - a boolean was returned by SSH2::_get_channel_packet()
     *
     * @param $stepResult
     *
     * @return bool
     */
    protected function processPartialOutput($stepResult): bool
    {
        if (is_string($stepResult)) {
            $this->output->add($stepResult);
        }

        $str = is_string($stepResult) ? $stepResult : '';

        // run various hooks to see if we should break out of the reading cycle
        // these should be run even if a boolean was returned
        if ($shouldBreak = $this->runReadCycleHooks($str)) {
            return true;
        }

        // if SSH2::read() returned a boolean, we should break anyway
        if (!is_string($stepResult)) {
            return true;
        }

        return false;
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
        $this->setCommand($command);
        $this->authenticateIfNecessary();
        $this->resetResults();

        $this->startTimer();
        $this->exec($command);
        $this->stopTimer();

        // get back the output (and error) as separate lines
        // (without cleaning)
        $this->collectOutput(false);

        $this->resetCommand();

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
        $this->setCommand($command);
        $this->authenticateIfNecessary();
        $this->resetResults();

        $this->startTimer();
        $this->writeAndSend((string)$command);
        $this->read();
        $this->stopTimer();

        // get back the output split into separate lines
        // and possibly separate stdout and stderr
        $this->collectOutput();

        $this->resetCommand();

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
     * If user has set the timeout condition to be 'timelimit' and the command is
     * already running longer than specified by the 'timeout' config value,
     * return true.
     *
     * @return bool
     */
    public function reachedTimeLimit(): bool
    {
        $timeout               = $this->getConfig('timeout');
        $condition             = $this->getConfig('timeout_condition');
        $timeSinceCommandStart = $this->timeSinceCommandStart();

        $result = (
            // user wants to timeout by 'timelimit'
            (SSHConfig::TIMEOUT_CONDITION_RUNNING_TIMELIMIT === $condition) &&
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
     * If user has set the timeout condition to be 'timeout' and we have been waiting
     * for output  already longer than specified by the 'timeout' config value,
     * return true.
     *
     * @return bool
     */
    public function reachedTimeout(): bool
    {
        $timeout             = $this->getConfig('timeout');
        $condition           = $this->getConfig('timeout_condition');
        $timeSinceLastPacket = $this->timeSinceLastResponse();

        return (
            // user wants to timeout by 'READING_TIMEOUT'
            (SSHConfig::TIMEOUT_CONDITION_READING_TIMEOUT === $condition) &&
            // user has set a non-zero timeout value
            $timeout &&
            // and this time has passed since the last packet was received
            ($timeSinceLastPacket >= $timeout)
        );
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
     * Check if either 'timeout' or 'timelimit' condition has been reached
     * while running this command.
     *
     * @return bool
     */
    public function isTimeout(): bool
    {
        return (bool)$this->getSSH2()->isTimeout();
    }

    /**
     * Check if the 'timelimit' timeout condition has specifically been reached
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
    public function timeSinceLastResponse(): float
    {
        $lastPacketTime = $this->getSSH2()->getLastResponseTime();

        // if no packet was yet received, we count from the time when command
        // started running
        if (!is_float($lastPacketTime)) {
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
    public function timeSinceCommandStart(): float
    {
        return microtime(true) - $this->getTimerStart();
    }

    /**
     * Clean up some variables after running a command.
     */
    protected function resetCommand()
    {
        $this->command = null;
    }
}

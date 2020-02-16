<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Traits\SSHConnection\ControlsCommandFlow;
use Neskodi\SSHCommander\Traits\SSHConnection\ControlsCommandTime;
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
use Neskodi\SSHCommander\Traits\HasReadCycleHooks;
use Neskodi\SSHCommander\Traits\StopsOnPrompt;
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
    use Loggable, Timer, ConfigAware,
        AuthenticatesSSH2, ConfiguresSSH2, InteractsWithSSH2,
        ControlsCommandTime, ControlsCommandFlow,
        HasOutputProcessor, HasReadCycleHooks, StopsOnPrompt;

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

        // by default, we will stop reading from the SSH stream as soon as
        // a prompt is detected on command line - unless told otherwise
        // by calling $this->stopsOnPrompt(false).
        $this->stopsOnPrompt();

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
        $config        = $command->getConfig();

        $this->setConfig($config);
        $this->setOutputProcessor(new SSHOutputProcessor($config));
        $this->setReadCycleHooks($command->getReadCycleHooks());
        $this->setTimeout($command->getConfig('timeout'));

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
     * Clean up some variables after running a command.
     */
    protected function resetCommand()
    {
        $this->command = null;
    }
}

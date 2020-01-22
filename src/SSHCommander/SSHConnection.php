<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Interfaces\ConfigAwareInterface;
use Neskodi\SSHCommander\Interfaces\LoggerAwareInterface;
use Neskodi\SSHCommander\Exceptions\CommandRunException;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Interfaces\TimerInterface;
use Neskodi\SSHCommander\Traits\ConfigAware;
use Neskodi\SSHCommander\Traits\Loggable;
use Neskodi\SSHCommander\Traits\Timer;
use Psr\Log\LoggerInterface;
use phpseclib\Crypt\RSA;
use phpseclib\Net\SSH2;

class SSHConnection implements
    SSHConnectionInterface,
    LoggerAwareInterface,
    ConfigAwareInterface,
    TimerInterface
{
    use Loggable, Timer, ConfigAware;

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
     * Choose the authentication algorithm (password or key) and perform the
     * authentication.
     *
     * @return bool true if authentication was successful, throws an exception
     * otherwise.
     *
     * @throws AuthenticationException
     */
    public function authenticate(): bool
    {
        $this->getConfig()->validate();

        $this->setLoginTimeout();

        $this->logConnecting();

        $credential = $this->getConfig()->selectCredential();

        $this->logSelectedCredential($credential);

        switch ($credential) {
            case SSHConfig::CREDENTIAL_KEY:
            case SSHConfig::CREDENTIAL_KEYFILE:
                $keyContents = $this->getConfig()->getKeyContents();
                @$result = $this->authenticateWithKey($keyContents);
                break;
            default:
                @$result = $this->authenticateWithPassword();
        }

        $this->resetTimeout();

        // throws AuthenticationException
        if (!$result) {
            $this->handleLoginError();
        } else {
            $this->info('Authenticated.');

            // clean out the interactive buffer
            $this->read();
        }

        // will only return true, because otherwise an Exception
        // would be thrown earlier
        return $result;
    }

    /**
     * Log that we are starting connection.
     */
    protected function logConnecting(): void
    {
        $this->info(
            'Connecting to {host}:{port}',
            [
                'host' => $this->getConfig()->getHost(),
                'port' => $this->getConfig()->getPort(),
            ]
        );
    }

    /**
     * Record which credential we use for authentication
     *
     * @param string $credential
     */
    protected function logSelectedCredential(string $credential): void
    {
        switch ($credential) {
            case SSHConfig::CREDENTIAL_KEY:
                $this->debug('SSH key is provided at runtime');
                break;
            case SSHConfig::CREDENTIAL_KEYFILE:
                $this->debug(sprintf(
                    'SSH key is loaded from file: %s',
                    $this->getConfig()->getKeyfile()
                ));
                break;
            case SSHConfig::CREDENTIAL_PASSWORD:
                $this->debug('SSH password is provided at runtime');
                break;
        }
    }

    /**
     * Create the RSA key object from string holding the key contents.
     *
     * @param string $keyContents
     *
     * @return RSA
     */
    protected function loadRSAKey(string $keyContents): RSA
    {
        $key = new RSA;
        if ($password = $this->getConfig()->getPassword()) {
            $key->setPassword($password);
        }
        $key->loadKey($keyContents);

        return $key;
    }

    /**
     * Load the RSA key from string and perform public key authentication.
     *
     * @param string $keyContents
     *
     * @return bool
     */
    protected function authenticateWithKey(string $keyContents): bool
    {
        $username = $this->getConfig()->getUser();
        $key      = $this->loadRSAKey($keyContents);

        $this->info(
            'Authenticating as user "{user}" with a private key.',
            ['user' => $username]
        );

        return $this->sshLogin($username, $key);
    }

    /**
     * Perform password-based authentication.
     *
     * @return bool
     */
    protected function authenticateWithPassword()
    {
        $username = $this->getConfig()->getUser();
        $password = $this->getConfig()->getPassword();

        $this->info(
            'Authenticating as user "{user}" with a password.',
            ['user' => $username]
        );

        return $this->sshLogin($username, $password);
    }

    /**
     * Return true is this connection has successfully passed authentication
     * with the remote host, false otherwise.
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return ($this->ssh2 && $this->ssh2->isAuthenticated());
    }

    /**
     * Handle login error gracefully by recording a message into our own
     * exception and throwing it. Do not pollute the command line.
     *
     * @throws AuthenticationException
     */
    protected function handleLoginError(): void
    {
        $error = $this->getSSH2()->getLastError() ?? error_get_last();

        if (is_array($error) && isset($error['message'])) {
            $error = $error['message'];
        }

        if (!is_string($error)) {
            // error is something unexpected, we will show the standard message
            $error = '';
        }

        if (false !== strpos($error, 'SSH_MSG_USERAUTH_FAILURE')) {
            // standard message about failed authentication is enough
            $error = '';
        }

        $exception = new AuthenticationException($error);
        $this->error($exception->getMessage(), ['exception' => $exception]);

        throw $exception;
    }

    /**
     * Phpseclib throws errors using user_error(). We will intercept this by
     * using our own error handler function that will throw a
     * CommandRunException.
     *
     * @param $errno
     * @param $errstr
     *
     * @throws CommandRunException
     */
    protected function handleSSH2Error($errno, $errstr)
    {
        throw new CommandRunException("$errno:$errstr");
    }

    /**
     * Execute a command or a series of commands. Proxy to phpseclib SSH2
     * exec() method.
     *
     * @param SSHCommandInterface $command the command to execute, or multiple
     *                                     commands separated by newline.
     *
     * @return SSHConnectionInterface
     *
     * @throws AuthenticationException
     */
    public function execIsolated(SSHCommandInterface $command): SSHConnectionInterface
    {
        if (!$this->isAuthenticated()) {
            $this->authenticate();
        }

        $this->resetOutput();
        $this->resetTimelimitStatus();
        $this->setConfig($command->getConfig());
        $this->setTimeout($command->getConfig('timeout_command'));

        $this->sshExec($command);

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
     *
     * @throws AuthenticationException
     */
    public function execInteractive(SSHCommandInterface $command): SSHConnectionInterface
    {
        if (!$this->isAuthenticated()) {
            $this->authenticate();
        }

        $this->resetOutput();
        $this->resetTimelimitStatus();
        $this->setConfig($command->getConfig());
        $this->setTimeout($command->getConfig('timeout_command'));

        $this->writeAndSend((string)$command);

        $output = $this->read();

        // clean out the command itself, any prompts, etc
        $output = $this->cleanCommandOutput($output, $command);

        $this->stdoutLines = $this->processOutput($output, $command);

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

        $ssh->setTimeout($this->getConfig('timeout_command'));

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

            if ($this->exceedsTimelimit()) {
                $this->isTimelimit = true;
                break;
            }

            if ($this->hasMarker($output)) {
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
     * If user has decided to force the timeout via the 'timelimit' config
     * option, and we have already exceeded this timeout, return true.
     *
     * @return bool
     */
    protected function exceedsTimelimit(): bool
    {
        // see if we really need to force the timeout
        if (!$timelimit = $this->getConfig('timelimit')) {
            return false;
        }

        return microtime(true) > ($this->getTimerStart() + $timelimit);
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
        return
            $this->usesMarkers() &&
            $this->hasExpectedOutputRegex($output, $this->markerRegex);
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
     */
    public function resetMarkers(): void
    {
        $this->markerRegex = null;
    }

    /**
     * See if we are told to detect markers in the output.
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
        $strPosFunction = function_exists('mb_Strpos') ? 'mb_Strpos' : 'strpos';

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
     * Send the terminate signal (CTRL+C) to the shell.
     */
    public function terminateCommand(): void
    {
        $this->write(SSHConfig::SIGNAL_TERMINATE);
    }

    /**
     * Send the 'suspend in background' signal (CTRL+Z) to the shell.
     */
    public function suspendCommand(): void
    {
        $this->write(SSHConfig::SIGNAL_BACKGROUND_SUSPEND);
    }

    /**
     * Tell phpseclib to write the characters to the channel. Handle errors
     * thrown by phpseclib on our side.
     *
     * @param string $chars
     *
     * @return bool
     */
    protected function sshWrite(string $chars)
    {
        set_error_handler([$this, 'handleSSH2Error']);

        $result = $this->getSSH2()->write($chars);

        restore_error_handler();

        return $result;
    }

    /**
     * Tell phpseclib to read packets from the channel and pass through the
     * result.
     *
     * @param string $chars
     * @param int    $mode
     *
     * @return bool|string
     */
    protected function sshRead(string $chars, int $mode)
    {
        set_error_handler([$this, 'handleSSH2Error']);

        $result = $this->getSSH2()->read($chars, $mode);

        restore_error_handler();

        return $result;
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
     * Execute the command via phpseclib and collect the returned lines
     * into an array.
     *
     * @param SSHCommandInterface $command
     */
    protected function sshExec(SSHCommandInterface $command): void
    {
        set_error_handler([$this, 'handleSSH2Error']);

        $ssh = $this->getSSH2();

        $ssh->exec((string)$command, function ($str) use ($command) {
            $this->stdoutLines = array_merge(
                $this->stdoutLines,
                $this->processOutput($str, $command)
            );
        });

        // don't forget to collect the error stream too
        if ($command->getConfig('separate_stderr')) {
            $this->stderrLines = $this->processOutput($ssh->getStdError(), $command);
        }

        $this->lastExitCode = $ssh->getExitStatus();

        restore_error_handler();
    }

    /**
     * Process an intermediate sequence of output characters before merging it
     * with the main array of output lines.
     *
     * @param SSHCommandInterface $command used to look up configuration
     * @param string              $output
     *
     * @return array
     */
    protected function processOutput(string $output, SSHCommandInterface $command): array
    {
        return $this->splitOutput($output, $command);
    }

    /**
     * Split output lines by a regular expression or simple character(s),
     * according to command configuration.
     *
     * @param string              $output
     * @param SSHCommandInterface $command
     *
     * @return array
     */
    protected function splitOutput(string $output, SSHCommandInterface $command): array
    {
        // see if user wants to split by regular expression
        if ($delim = $command->getConfig('delimiter_split_output_regex')) {
            return preg_split($delim, $output) ?: [];
        }

        // see if user wants to explode by a simple delimiter
        if ($delim = $command->getConfig('delimiter_split_output')) {
            return explode($delim, $output) ?: [];
        }

        // otherwise no splitting can be performed
        return [$output];
    }

    /**
     * Tell phpseclib to authenticate the connection using the credentials
     * provided.
     *
     * @param string $username
     * @param        $credential
     *
     * @return bool
     */
    protected function sshLogin(string $username, $credential): bool
    {
        set_error_handler([$this, 'handleSSH2Error']);

        $result = $this->getSSH2()->login($username, $credential);

        restore_error_handler();

        return $result;
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
     * Set the timeout (in seconds) for the next SSH2 operation.
     *
     * @param int $timeout timeout in seconds
     *
     * @return $this
     */
    public function setTimeout(int $timeout): SSHConnectionInterface
    {
        $this->getSSH2()->setTimeout($timeout);

        return $this;
    }

    /**
     * Reset the timeout to its default value.
     *
     * @return $this
     */
    public function resetTimeout(): SSHConnectionInterface
    {
        $this->setTimeout($this->getConfig()->getDefault('timeout_command'));

        return $this;
    }

    /**
     * Automatically set the timeout from the config value "timeout_connect".
     *
     * @return $this
     */
    protected function setLoginTimeout(): SSHConnectionInterface
    {
        $this->setTimeoutFromConfig('timeout_connect');

        return $this;
    }

    /**
     * Set timeout automatically based on the relevant configuration value.
     *
     * @param string $configKey the config key to read the timeout from (in seconds)
     *
     * @return $this
     */
    protected function setTimeoutFromConfig(string $configKey): SSHConnectionInterface
    {
        if ($timeout = $this->getConfig($configKey)) {
            $timeout = (int)$timeout;
            $this->setTimeout($timeout);
        }

        return $this;
    }

    /**
     * Clear all traces of previous commands.
     *
     * @return $this
     */
    public function resetOutput(): SSHConnectionInterface
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
    public function resetTimelimitStatus(): SSHConnectionInterface
    {
        $this->isTimelimit = false;

        return $this;
    }

    /**
     * Reset some configuration after running a single command back to default
     * values.
     *
     * @return $this
     */
    public function resetCommandConfig(): SSHConnectionInterface
    {
        return $this->resetTimeout()
                    ->resetQuietMode();
    }

    /**
     * By default, quiet mode is disabled in phpseclib. Return it to that state.
     *
     * @return $this
     */
    public function resetQuietMode(): SSHConnectionInterface
    {
        $this->getSSH2()->disableQuietMode();

        return $this;
    }

    /**
     * Check if timeout flag has been set by SSH2 object.
     *
     * @return bool
     */
    public function isTimeout(): bool
    {
        return (bool)$this->getSSH2()->isTimeout();
    }

    /**
     * Check if command has exceeded the timelimit set in configuration.
     *
     * @return bool
     */
    public function isTimelimit(): bool
    {
        return $this->isTimelimit;
    }

    /**
     * Check if either timeout or timelimit condition has been reached while
     * running this command.
     *
     * @return bool
     */
    public function isTimeoutOrTimelimit(): bool
    {
        return $this->isTimeout() || $this->isTimelimit();
    }
}

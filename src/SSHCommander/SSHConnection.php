<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Interfaces\ConfigAwareInterface;
use Neskodi\SSHCommander\Interfaces\LoggerAwareInterface;
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

    const DEFAULT_TIMEOUT = 10;

    /**
     * @var SSH2
     */
    protected $ssh2;

    /**
     * @var bool
     */
    protected $authenticated = false;

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
            $this->processLoginError();
        } else {
            $this->info('Authenticated.');
            $this->authenticated = true;

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
     * Handle login error gracefully by recording a message into our own
     * exception and throwing it. Do not pollute the command line.
     *
     * @throws AuthenticationException
     */
    protected function processLoginError(): void
    {
        $errorText = error_get_last();
        $message   = $errorText ? $errorText['message'] : '';

        $exception = new AuthenticationException($message);
        $this->error($exception->getMessage(), ['exception' => $exception]);

        throw $exception;
    }

    /**
     * Return true is this connection has successfully passed authentication
     * with the remote host, false otherwise.
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return (bool)$this->authenticated;
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
    public function exec(SSHCommandInterface $command): SSHConnectionInterface
    {
        if (!$this->authenticated) {
            $this->authenticate();
        }

        $this->resetOutput();
        $this->setConfig($command->getConfig());

        $this->sshExec($command);

        return $this;
    }

    /**
     * Write command and read the output until we have a prompt or a timeout.
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
        if (!$this->authenticated) {
            $this->authenticate();
        }

        $this->resetOutput();
        $this->isTimeout = false;
        $this->setConfig($command->getConfig());
        $this->cleanCommandBuffer();

        // Commands MUST be glued by ';' to produce only one command prompt
        // so we get all commands as single string
        $this->writeAndSend($command->singleString());

        $delim  = $command->getConfig('delimiter_split_output');
        $output = $this->read();
        $output = $this->cleanCommandOutput($output, $command);

        $this->stdoutLines = explode($delim, $output);
        $this->checkLastExitCode();

        return $this;
    }

    protected function cleanCommandBuffer()
    {
        $ssh = $this->getSSH2();
        $ssh->setTimeout(2);
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
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    protected function checkLastExitCode(): void
    {
        if ($this->getConfig('disable_exit_code_check')) {
            $this->lastExitCode = null;
        } else {
            $command = new SSHCommand('echo $?', $this->getConfig());
            $this->writeAndSend($command);
            $output             = $this->read();
            $this->lastExitCode = (int)$this->cleanCommandOutput($output, $command);
        }
    }

    /**
     * SSH2::read() returns the entire interactive buffer, including the command
     * itself and the command prompt in the end. We are only interested in the
     * command output, so we will delete these artifacts.
     *
     * @param string              $output
     * @param SSHCommandInterface $command
     *
     * @return false|string|string[]|null
     */
    protected function cleanCommandOutput(string $output, SSHCommandInterface $command)
    {
        // clean out the command itself from the beginning
        $delim        = '\r?\n';
        $commandChars = '^' . preg_quote($command->singleString(), '/') . $delim;
        $commandRegex = "/$commandChars/";
        $output       = preg_replace($commandRegex, '', $output);

        $promptRegex = $this->getConfig()->getPromptRegex();
        // clean out the command prompt from the end
        $output = preg_replace($promptRegex, '', $output);

        return $output;
    }

    public function read()
    {
        $this->startTimer();
        $output = '';

        while ($str = $this->sshRead('', SSH2::READ_NEXT)) {
            $output .= $str;
            if ($this->exceedsForcedTimeout()) {
                $this->isTimeout = true;
                break;
            } elseif ($this->hasPrompt($output)) {
                $this->isTimeout = false;
                break;
            }
        }

        $this->stopTimer();

        return $output;
    }

    /**
     * If user has decided to force the timeout via the 'force_timeout' config
     * option, and we have already exceeded this timeout, return true.
     *
     * @return bool
     */
    protected function exceedsForcedTimeout(): bool
    {
        // see if we really need to force the timeout
        if (!$this->getConfig('force_timeout')) {
            return false;
        }

        $timeout = $this->getConfig('timeout_command');

        // see if timeout is a falsy value, including false, 0, and null
        // in this case we assume user doesn't want a timeout
        if (!$timeout) {
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
     * Check if current received output from the command already contains a
     * substring expected by user.
     *
     * @param string $output
     * @param string $expect
     *
     * @return bool
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
     *
     * @throws AuthenticationException
     */
    public function write(string $chars)
    {
        return $this->sshWrite($chars);
    }

    protected function sshWrite(string $chars)
    {
        return $this->getSSH2()->write($chars);
    }

    protected function sshRead(string $chars, int $mode)
    {
        return $this->getSSH2()->read($chars, $mode);
    }

    /**
     * Write a sequence of characters into the command line and submit them for
     * execution by appending a line feed "\n" at the end (if not already there)
     *
     * @param string $chars
     *
     * @return bool
     *
     * @throws AuthenticationException
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
     * into an array
     *
     * @param SSHCommandInterface $command
     * @param string              $delim
     */
    protected function sshExec(SSHCommandInterface $command): void
    {
        $ssh = $this->getSSH2();

        // the delimiter used to split output lines, by default \n
        $delim = $command->getConfig('delimiter_split_output');

        $ssh->exec((string)$command, function ($str) use ($delim) {
            $this->stdoutLines = array_merge(
                $this->stdoutLines,
                explode($delim, $str)
            );
        });

        // don't forget to collect the error stream too
        if ($command->getConfig('separate_stderr')) {
            $this->stderrLines = explode($delim, $ssh->getStdError());
        }

        $this->lastExitCode = $ssh->getExitStatus();
    }

    protected function sshLogin(string $username, $credential): bool
    {
        return $this->getSSH2()->login($username, $credential);
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
        $this->setTimeout(static::DEFAULT_TIMEOUT);

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
     * Check if timeout flag has been set on this connection (in case of forced
     * timeout) or by SSH2 object.
     *
     * @return bool
     */
    public function isTimeout(): bool
    {
        return $this->isTimeout || (bool)$this->getSSH2()->isTimeout();
    }
}

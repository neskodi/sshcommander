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
        $error = $this->getSSH2()->getLastError() ?? error_get_last();

        if (is_array($error) && isset($errorText['message'])) {
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

        $this->sshExec($command);

        return $this;
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
     */
    public function resetOutput(): void
    {
        $this->stdoutLines = $this->stderrLines = [];

        $this->lastExitCode = null;
    }

    /**
     * Reset some configuration after running a single command back to default
     * values.
     */
    public function resetCommandConfig(): void
    {
        $this->resetTimeout();

        $this->resetQuietMode();
    }

    /**
     * By default, quiet mode is disabled in phpseclib. Return it to that state.
     */
    public function resetQuietMode(): void
    {
        $this->getSSH2()->disableQuietMode();
    }
}

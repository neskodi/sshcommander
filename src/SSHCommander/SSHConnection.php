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
     * Automatically set the timeout from the config value "timeout_command".
     *
     * @return $this
     */
    protected function setCommandTimeout(): SSHConnectionInterface
    {
        $this->setTimeoutFromConfig('timeout_command');

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

        if ($keyContents = $this->getKeyContents()) {
            @$result = $this->authenticateWithKey($keyContents);
        } else {
            @$result = $this->authenticateWithPassword();
        }

        $this->resetTimeout();

        if (!$result) {
            $this->processLoginError();
        }

        $this->info('Authenticated.');

        $this->authenticated = true;

        return $result;
    }

    /**
     * Get private key contents from the config object. May be stored directly
     * under 'key' or in the file pointed to by 'keyfile'.
     *
     * @return false|string|null
     */
    protected function getKeyContents(): ?string
    {
        $keyContents = null;

        if ($key = $this->getConfig()->getKey()) {
            $this->debug('Key contents provided via configuration.');
            $keyContents = $key;
        } elseif ($keyfile = $this->getConfig()->getKeyfile()) {
            $this->debug(
                'Reading key contents from file: {file}',
                ['file' => $keyfile]
            );
            $keyContents = file_get_contents($keyfile);
        }

        return $keyContents;
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
            'Authenticating as user "{user}" with a public key.',
            ['user' => $username]
        );

        return $this->getSSH2()->login($username, $key);
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

        return $this->getSSH2()->login($username, $password);
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
     * Execute a command or a series of commands. Proxy to phpseclib SSH2
     * exec() method.
     *
     * @param SSHCommandInterface $command the command to execute, or multiple
     *                                   commands separated by newline.
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

        $this->run($command);

        return $this;
    }

    /**
     * Execute the command using the ssh2 object.
     *
     * @param SSHCommandInterface $command
     */
    protected function run(SSHCommandInterface $command): void
    {
        // the delimiter used to split output lines, by default \n
        $delim = $command->getConfig('delimiter_split_output');

        $this->setCommandTimeout();
        $ssh = $this->getSSH2();

        // clean all data from previous commands
        $this->resetOutput();

        $this->logCommandStart($command);
        $this->startTimer();

        // execute the command via phpseclib and collect the returned lines
        // into an array
        $ssh->exec((string)$command, function ($str) use ($delim) {
            $this->stdoutLines = array_merge(
                $this->stdoutLines,
                explode($delim, $str)
            );
        });

        // stop the timer and log command end
        $this->logCommandEnd($this->endTimer());

        // remember the exit code
        $this->lastExitCode = (int)$ssh->getExitStatus();

        // don't forget to collect the error stream too
        if ($command->getConfig('separate_stderr')) {
            $this->stderrLines = explode($delim, $ssh->getStdError());
        }

        $this->resetTimeout();
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
     * Clear all traces of previous commands.
     */
    public function resetOutput(): void
    {
        $this->stdoutLines = $this->stderrLines = [];

        $this->lastExitCode = null;
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
        return $this->lastExitCode;
    }

    /**
     * Log the event of running the command.
     *
     * @param SSHCommandInterface $command
     */
    protected function logCommandStart(SSHCommandInterface $command): void
    {
        $this->info(sprintf(
                'Running command: %s',
                $command->toLoggableString())
        );
    }

    /**
     * Log command completion along with the time it took to run.
     *
     * @param float $seconds
     */
    protected function logCommandEnd(float $seconds): void
    {
        $this->info('Command completed in {seconds} seconds', [
            'seconds' => $seconds,
        ]);
    }
}

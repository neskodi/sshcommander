<?php

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Interfaces\CommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Exceptions\CommandRunException;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Interfaces\CommandInterface;
use Neskodi\SSHCommander\Traits\Loggable;
use Neskodi\SSHCommander\Traits\Timer;
use Psr\Log\LoggerInterface;
use phpseclib\Crypt\RSA;
use phpseclib\Net\SSH2;

class SSHConnection implements SSHConnectionInterface
{
    use Loggable, Timer;

    const DEFAULT_TIMEOUT = 10;

    /**
     * @var SSHConfigInterface
     */
    protected $config;

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
    protected $outputLines = [];

    /**
     * @var CommandInterface
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
     * Fluent setter for SSHConfig object that contains credentials used for
     * connection.
     *
     * @param SSHConfigInterface $config
     *
     * @return SSHConfigInterface
     */
    public function setConfig(SSHConfigInterface $config): SSHConnectionInterface
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Get the SSHConfig object used by this connection, or a specific key from
     * that config object.
     *
     * @param string|null $key
     *
     * @return SSHConfigInterface
     */
    public function getConfig(?string $key = null)
    {
        return $key ? $this->config->get($key) : $this->config;
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
     * @param CommandInterface $command  the command to execute, or multiple
     *                                   commands separated by newline.
     *
     * @return CommandResultInterface
     *
     * @throws CommandRunException
     *
     * @throws AuthenticationException
     */
    public function exec(CommandInterface $command): CommandResultInterface
    {
        if (!$this->authenticated) {
            $this->authenticate();
        }

        $result = $this->setCommand($command)
                       ->prepare()
                       ->run()
                       ->collectResult();

        $this->logResult($result);

        return $result;
    }

    /**
     * Set the command to execute.
     *
     * @param CommandInterface $command
     *
     * @return $this
     */
    protected function setCommand(CommandInterface $command): SSHConnectionInterface
    {
        $this->command = $command;

        return $this;
    }

    /**
     * Prepare to execute by setting additional options to the ssh2 object.
     *
     * @return $this
     *
     * @throws CommandRunException
     */
    protected function prepare(): SSHConnectionInterface
    {
        // safety net for people extending this class
        $this->requireCommand();

        // if user wants stderr as separate stream or wants to suppress it
        // altogether, tell phpseclib about it
        if (
            $this->command->getOption('separate_stderr') ||
            $this->command->getOption('suppress_stderr')
        ) {
            $this->getSSH2()->enableQuietMode();
        }

        return $this;
    }

    /**
     * Execute the command using the ssh2 object.
     *
     * @return $this
     *
     * @throws CommandRunException
     */
    protected function run(): SSHConnectionInterface
    {
        // safety net for people extending this class
        $this->requireCommand();

        // the delimiter used to split output lines, by default \n
        $delim = $this->command->getOption('delimiter_split_output');

        // reset the output lines
        $this->outputLines = [];

        $this->setCommandTimeout();

        $this->info(sprintf(
                'Running command: %s',
                $this->command->toLoggableString())
        );

        // execute the command via phpseclib and collect the returned lines
        // into an array
        $ssh = $this->getSSH2();

        $this->startTimer();

        $ssh->exec((string)$this->command, function ($str) use ($delim) {
            $this->outputLines = array_merge(
                $this->outputLines,
                explode($delim, $str)
            );
        });

        $this->info('Command completed in {seconds} seconds', [
            'seconds' => $this->endTimer(),
        ]);

        $this->resetTimeout();

        return $this;
    }

    /**
     * Collect the execution result into the result object.
     *
     * @return CommandResultInterface
     *
     * @throws CommandRunException
     */
    protected function collectResult(): CommandResultInterface
    {
        // safety net for people extending this class
        $this->requireCommand();

        // the delimiter used to split output lines, by default \n
        $delim = $this->command->getOption('delimiter_split_output');
        $ssh   = $this->getSSH2();

        // structure the result
        $result = new CommandResult($this->command);

        if ($logger = $this->getLogger()) {
            $result->setLogger($logger);
        }

        $result->setExitCode((int)$ssh->getExitStatus())
               ->setOutput($this->outputLines);

        // get the error stream separately, if we were asked to
        if ($this->command->getOption('separate_stderr')) {
            $result->setErrorOutput(explode($delim, $ssh->getStdError()));
        }

        return $result;
    }

    /**
     * Throw an exception when trying to run a command before setting it.
     *
     * @throws CommandRunException
     */
    protected function requireCommand()
    {
        if (!$this->command instanceof CommandInterface) {
            throw new CommandRunException('Command is not set');
        }
    }

    /**
     * Handle login error gracefully by recording a message into our own
     * exception and throwing it. Do not pollute the command line.
     *
     * @throws AuthenticationException
     */
    protected function processLoginError(): void
    {
        if ($this->getSSH2()->isTimeout()) {
            $message = sprintf(
                'Timed out after %d seconds',
                $this->getConfig('timeout_connect')
            );
        } else {
            $errorText = error_get_last();
            $message   = $errorText ? $errorText['message'] : '';
        }

        $exception = new AuthenticationException($message);
        $this->error($message, ['exception' => $exception]);

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
     * Log command exit code and output:
     * - notice / info: only exit code in case of error
     * - debug: any exit code and entire output
     *
     * @param CommandResultInterface $result
     */
    protected function logResult(CommandResultInterface $result): void
    {
        $status = $result->getStatus();
        $code   = $result->getExitCode();
        if ($result->isError()) {
            // error is logged on the notice level
            $this->notice(
                'Command returned error status: {status}',
                ['status' => $result->getExitCode()]
            );
        } else {
            // success is logged on the debug level only
            $this->debug(
                'Command returned exit status: {status} (code {code})',
                compact('status', 'code')
            );
        }

        // log the entire command output (debug level only)
        $result->logResult();
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
}

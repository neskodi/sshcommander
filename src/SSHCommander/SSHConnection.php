<?php

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Interfaces\CommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Exceptions\CommandRunException;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Interfaces\CommandInterface;
use phpseclib\Crypt\RSA;
use phpseclib\Net\SSH2;

class SSHConnection implements SSHConnectionInterface
{
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
    protected $authenticationStatus = false;

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
     * @param SSHConfigInterface $config
     * @param bool               $autologin
     *
     * @throws AuthenticationException
     */
    public function __construct(SSHConfigInterface $config)
    {
        $this->setConfig($config);

        if ($config->get('autologin')) {
            $this->authenticate();
        }
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
     * Get the SSHConfig object used by this connection.
     *
     * @return SSHConfigInterface
     */
    public function getConfig(): SSHConfigInterface
    {
        return $this->config;
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
                $this->config->getHost(),
                $this->config->getPort(),
            ];

            $this->ssh2 = new SSH2($host, $port);
        }

        return $this->ssh2;
    }

    /**
     * Choose the authentication algorithm (password or key) and perform the
     * authentication.
     *
     * @return bool true if authentication was successful, false otherwise.
     *
     * @throws AuthenticationException
     */
    public function authenticate()
    {
        if ($keyContents = $this->getKeyContents()) {
            $result = $this->authenticateWithKey($keyContents);
        } else {
            $result = $this->authenticateWithPassword();
        }

        if (!$result) {
            throw new AuthenticationException;
        }

        $this->authenticationStatus = true;

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

        if ($key = $this->config->getKey()) {
            $keyContents = $key;
        } elseif ($keyfile = $this->config->getKeyfile()) {
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
        $username = $this->config->getUser();
        $key      = $this->loadRSAKey($keyContents);

        return $this->getSSH2()->login($username, $key);
    }

    /**
     * Perform password-based authentication.
     *
     * @return bool
     */
    protected function authenticateWithPassword()
    {
        $username = $this->config->getUser();
        $password = $this->config->getPassword();

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
        if ($password = $this->config->getPassword()) {
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
     */
    public function exec(CommandInterface $command): CommandResultInterface
    {
        return $this->setCommand($command)
                    ->prepare()
                    ->run()
                    ->collectResult();
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
        $ssh = $this->getSSH2();

        $this->outputLines = [];

        // execute the command via phpseclib and collect the returned lines
        // into an array
        $ssh->exec((string)$this->command, function ($str) use ($delim) {
            $this->outputLines = array_merge(
                $this->outputLines,
                explode($delim, $str)
            );
        });

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
        $ssh = $this->getSSH2();

        // structure the result
        $result = (new CommandResult($this->command))
            ->setExitCode((int)$ssh->getExitStatus())
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
}

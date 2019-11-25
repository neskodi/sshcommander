<?php

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use phpseclib\Crypt\RSA;
use phpseclib\Net\SSH2;
use Closure;

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
     * SSHConnection constructor.
     *
     * @param SSHConfigInterface $config
     * @param bool               $autologin
     *
     * @throws AuthenticationException
     */
    public function __construct(SSHConfigInterface $config, $autologin = true)
    {
        $this->setConfig($config);

        if ($autologin) {
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
     * @param string       $command  the command to execute, or multiple
     *                               commands separated by newline.
     * @param Closure|null $callback the callback function to pass fragments of
     *                               command output to.
     *
     * @return string|bool the output of the command as string, or false in case
     *                     of failure.
     */
    public function exec(string $command, ?Closure $callback = null)
    {
        return $this->getSSH2()->exec($command, $callback);
    }
}

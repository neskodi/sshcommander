<?php

namespace Neskodi\SSHCommander\Traits\SSHConnection;

use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\SSHConfig;
use phpseclib\Crypt\RSA;
use phpseclib\Net\SSH2;

trait AuthenticatesSSH2
{
    protected $ssh2;

    /** declare external methods that this trait relies upon **/

    abstract public function getSSH2(): SSH2;

    abstract public function getConfig(?string $param = null);

    abstract public function setTimeout(int $timeout);

    abstract public function resetTimeout();

    abstract public function read(): string;

    abstract public function info($message, array $context = array());

    abstract public function debug($message, array $context = array());

    /** end declaring external required methods **/

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
     * A shortcut to authenticate only if the connection is not authenticated yet.
     *
     * @noinspection PhpUnhandledExceptionInspection
     */
    public function authenticateIfNecessary(): void
    {
        if (!$this->isAuthenticated()) {
            $this->authenticate();
        }
    }

    /**
     * Return true is this connection has successfully passed authentication
     * with the remote host, false otherwise.
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        if (!$ssh = $this->getSSH2()) {
            return false;
        }

        return $ssh->isAuthenticated();
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
        set_error_handler([$this, 'handleLoginError']);

        $result = $this->getSSH2()->login($username, $credential);

        restore_error_handler();

        return $result;
    }

    /**
     * Handle login error gracefully by recording a message into our own
     * exception and throwing it. Do not pollute the command line.
     *
     * @throws AuthenticationException
     */
    public function handleLoginError(): void
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
     * Automatically set the timeout from the config value "timeout_connect".
     *
     * @return $this
     */
    protected function setLoginTimeout()
    {
        if ($timeout = $this->getConfig('timeout_connect')) {
            $timeout = (int)$timeout;
            $this->setTimeout($timeout);
        }

        return $this;
    }
}

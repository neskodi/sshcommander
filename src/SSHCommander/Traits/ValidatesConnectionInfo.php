<?php

namespace Neskodi\SSHCommander\Traits;

use Neskodi\SSHCommander\Exceptions\ConfigValidationException;

trait ValidatesConnectionInfo
{
    /**
     * Verify that the host address provided by the user is valid.
     *
     * We don't check against IP address or domain name syntax here, we are just
     * making sure it's present and not empty. Throw an exception otherwise.
     *
     * @param array $config the entire array - to know the validation context
     *
     * @return $this
     *
     * @throws ConfigValidationException
     */
    protected function validateHost(array $config)
    {
        $validationResult = $this->requireNonEmptyString($config, 'host');

        if (true !== $validationResult) {
            throw new ConfigValidationException($validationResult);
        }

        return $this;
    }

    /**
     * Verify that the port provided by the user (if any) is a valid numeric
     * value. (It will be cast to integer later in the "prepare()" method).
     * Throw an exception otherwise.
     *
     * @param array $config the entire array - to know the validation context
     *
     * @return $this
     *
     * @throws ConfigValidationException
     */
    protected function validatePort(array $config)
    {
        if (array_key_exists('port', $config) && !is_numeric($config['port'])) {
            $message  = 'port must be an integer, %s given';
            $provided = is_scalar($config['port'])
                ? sprintf('"%s"', $config['port'])
                : gettype($config['port']);
            $message  = sprintf($message, $provided);
            throw new ConfigValidationException($message);
        }

        return $this;
    }

    /**
     * Verify that the SSH username, if it is required for a remote connection,
     * is present and not empty in the config array.
     * Throw an exception otherwise.
     *
     * @param array $config the entire array - to know the validation context
     *
     * @return $this
     *
     * @throws ConfigValidationException
     */
    protected function validateUser(array $config)
    {
        $validationResult = $this->requireNonEmptyString($config, 'user', 'SSH username');

        if (true !== $validationResult) {
            throw new ConfigValidationException($validationResult);
        }

        return $this;
    }

    /**
     * Verify that keyfile, if present in the user-provided config, is an
     * existing and readable file. Throw an exception otherwise.
     *
     * @param array $config the entire array - to know the validation context
     *
     * @return $this
     *
     * @throws ConfigValidationException
     */
    protected function validateKeyfile(array $config)
    {
        if (!$this->needsToValidateKeyfile($config)) {
            return $this;
        }

        $error = null;

        if (!is_file($config['keyfile'])) {
            $error = 'file "%s" (provided as the SSH key) does not exist.';
            $error = sprintf($error, $config['keyfile']);
        } elseif (!is_readable($config['keyfile'])) {
            $error = 'file "%s" (provided as the SSH key) is not readable ' .
                     '(permission issue?)';
            $error = sprintf($error, $config['keyfile']);
        }

        if ($error) {
            throw new ConfigValidationException($error);
        }

        return $this;
    }

    /**
     * We don't require a non-empty string here because ssh servers can
     * PermitEmptyPasswords, but you can override this logic in your extending
     * class of course.
     *
     * @param array $config
     *
     * @return $this
     */
    protected function validatePassword(array $config)
    {
        if (!$this->needsToValidatePassword($config)) {
            return $this;
        }

        if (array_key_exists('password', $config)) {
            if (!is_string($config['password'])) {
                $error = sprintf(
                    'SSH password provided must be a string, %s given',
                    gettype($config['password'])
                );

                throw new ConfigValidationException($error);
            }
        }

        return $this;
    }

    /**
     * Verify that the SSH private key provided by user is valid.
     *
     * @param array $config
     *
     * @return $this
     */
    protected function validateKey(array $config)
    {
        if (!$this->needsToValidateKey($config)) {
            return $this;
        }

        $validationResult = $this->requireNonEmptyString($config, 'key');

        if (true !== $validationResult) {
            throw new ConfigValidationException($validationResult);
        }

        return $this;
    }

    /**
     * Check that the required key is present in the array and is a regular
     * non-empty string.
     *
     * @param array       $config
     * @param string      $key
     * @param string|null $userFriendlyName
     *
     * @return bool|string true if all checks passed, error message otherwise.
     */
    protected function requireNonEmptyString(
        array $config,
        string $key,
        ?string $userFriendlyName = null
    ) {
        $result           = true;
        $userFriendlyName = $userFriendlyName ?? $key;

        if (!array_key_exists($key, $config)) {
            $result = sprintf(
                '%s is required for remote connections', $userFriendlyName
            );
        } elseif (!is_string($config[$key])) {
            $result = sprintf(
                '%s provided must be a string, %s given',
                $userFriendlyName,
                gettype($config[$key])
            );
        } elseif (empty(trim($config[$key]))) {
            $result = sprintf(
                '%s is required for remote connections, empty string given',
                $userFriendlyName
            );
        }

        return $result;
    }

    protected function needsToValidateKey(array $context): bool
    {
        return (
            !$this->contextHasValidPassword($context) &&
            !$this->contextHasValidKeyfile($context)
        );
    }

    protected function needsToValidateKeyfile(array $context): bool
    {
        return (
            !$this->contextHasValidPassword($context) &&
            !$this->contextHasValidKey($context)
        );
    }

    protected function needsToValidatePassword(array $context): bool
    {
        return (
            !$this->contextHasValidKey($context) &&
            !$this->contextHasValidKeyfile($context)
        );
    }

    protected function contextHasValidPassword(array $context): bool
    {
        return isset($context['password']) &&
               is_string($context['password']);
    }

    protected function contextHasValidKey(array $context): bool
    {
        return isset($context['key']) &&
               is_string($context['key']) &&
               !empty(trim($context['key']));
    }

    protected function contextHasValidKeyfile(array $context): bool
    {
        return isset($context['keyfile']) &&
               is_string($context['keyfile']) &&
               !empty(trim($context['keyfile']));
    }
}

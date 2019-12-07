<?php /** @noinspection PhpIncludeInspection */

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Exceptions\ConfigFileMissingException;
use Neskodi\SSHCommander\Exceptions\ConfigValidationException;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use BadMethodCallException;

class SSHConfig implements SSHConfigInterface
{
    /**
     * Location of the config file.
     *
     * @var string
     */
    protected static $configFileLocation;

    /**
     * The configuration storage.
     *
     * @var array
     */
    protected $config = [];

    /**
     * SSHConfig constructor.
     *
     * Load default configuration values from the default configuration file
     * and if provided, override with user-provided file and / or runtime
     * specific settings.
     *
     * This way we ensure some required settings that SSHCommander relies upon
     * are always present.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->loadDefaultConfigFile()
             ->loadUserConfigFile()
             ->validate($config)
             ->setFromArray($config);
    }

    /**
     * Load the default configuration file.
     *
     * @return $this
     */
    protected function loadDefaultConfigFile(): SSHConfigInterface
    {
        $file = static::getDefaultConfigFileLocation();

        if (!file_exists($file) || !is_readable($file)) {
            throw new ConfigFileMissingException($file);
        }

        $this->loadConfigFile($file);

        return $this;
    }

    /**
     * If user has provided their own configuration file, override the default
     * values with values read from user's file.
     *
     * @return $this
     */
    protected function loadUserConfigFile(): SSHConfigInterface
    {
        if ($file = static::getConfigFileLocation()) {
            $this->loadConfigFile($file);
        }

        return $this;
    }

    /**
     * Load the specified config file into $this->config.
     *
     * @param string $file
     */
    protected function loadConfigFile(string $file): void
    {
        if (file_exists($file) && is_readable($file)) {
            $this->setFromArray((array)include($file));
        }
    }

    /**
     * Import the provided array into $this->config.
     *
     * @param array $config
     *
     * @return SSHConfigInterface
     */
    public function setFromArray(array $config): SSHConfigInterface
    {
        foreach ($config as $key => $value) {
            $this->config[$key] = $this->prepare($config, $key);
        }

        return $this;
    }

    /**
     * Validate the passed config array. In case of invalid input,
     * exceptions will be thrown.
     *
     *
     * @param array $config
     *
     * @return SSHConfigInterface
     * @throws ConfigValidationException
     */
    public function validate(array $config = []): SSHConfigInterface
    {
        $this->validateHost($config)
             ->validatePort($config)
             ->validateUser($config)
             ->validateKeyfile($config);

        return $this;
    }

    /**
     * Sanitize the values that arrived from user.
     *
     * @param array  $config array with all values to provide context.
     * @param string $param  the parameter that we are sanitizing now.
     *
     * @return mixed|null
     */
    protected function prepare(array $config, string $param)
    {
        switch ($param) {
            case 'port':
                return (int)$config[$param];
            default:
                return $config[$param];
        }
    }

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
    protected function validateHost(array $config): SSHConfigInterface
    {
        $error = null;

        if (!array_key_exists('host', $config)) {
            $error = 'host is required for an SSH connection';
        } elseif (!is_string($config['host'])) {
            $error = 'host provided for SSH connection must be a string, %s given';
            $error = sprintf($error, gettype($config['host']));
        } elseif (empty(trim($config['host']))) {
            $error = 'host is required for an SSH connection, empty string given';
        }

        if ($error) {
            throw new ConfigValidationException($error);
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
    protected function validatePort(array $config): SSHConfigInterface
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
    protected function validateUser(array $config): SSHConfigInterface
    {
        $error = null;

        if (!array_key_exists('user', $config)) {
            $error = 'SSH username is required for remote connections';
        } elseif (!is_string($config['user'])) {
            $error = 'SSH username provided must be a string, %s given';
            $error = sprintf($error, gettype($config['user']));
        } elseif (empty(trim($config['user']))) {
            $error = 'SSH username is required for remote connections';
        }

        if ($error) {
            throw new ConfigValidationException($error);
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
    protected function validateKeyfile(array $config): SSHConfigInterface
    {
        $error = null;

        if (!array_key_exists('keyfile', $config)) {
            // keyfile isn't used, no need to validate
            return $this;
        }

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
     * Set an arbitrary config parameter.
     *
     * @param string $param the name of parameter to set.
     * @param mixed  $value the value to set the parameter to.
     */
    public function set(string $param, $value): void
    {
        $method = 'set' . Utils::camelCase($param);
        if (method_exists($this, $method)) {
            // we have a special setter for this
            $this->$method($value);
        } else {
            // just throw the value into the config
            $this->config[$param] = $value;
        }
    }

    /**
     * Get an arbitrary config parameter, with an ability to specify a fallback
     * value if this parameter does not exist in the config array.
     *
     * @param string $param
     * @param null   $default
     *
     * @return mixed|null
     */
    public function get(string $param, $default = null)
    {
        $method = 'get' . Utils::camelCase($param);
        if (method_exists($this, $method)) {
            // we have a special getter for this
            return $this->$method($param, $default);
        } else {
            return array_key_exists($param, $this->config)
                ? $this->config[$param]
                : $default;
        }
    }

    /**
     * This method is here mainly to intercept calls to getters such as
     * "getHost()" or "getPassword()"
     *
     * @param string $name      the parameter to get
     * @param array  $arguments arguments as array
     *
     * @return mixed|null
     */
    public function __call(string $name, array $arguments)
    {
        if (0 === strpos($name, 'get')) {
            $param   = Utils::snakeCase(substr($name, 3));
            $default = count($arguments) ? $arguments[0] : null;

            return $this->get($param, $default);
        }

        throw new BadMethodCallException(sprintf(
            'Method "%s" does not exist in class "%s"',
            $name,
            get_class($this)
        ));
    }

    /**
     * Return the entire config as an array.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Set the location of the config file that is used for reading the default
     * settings.
     *
     * @param string $location
     */
    public static function setConfigFileLocation(string $location): void
    {
        static::$configFileLocation = $location;
    }

    /**
     * Get the config file location - will return the location set by user,
     * if any, otherwise the default config file location from package source.
     *
     * @return string|null
     */
    public static function getConfigFileLocation(): string
    {
        return static::$configFileLocation ??
               static::getDefaultConfigFileLocation();
    }

    /**
     * Some tests need to reset config file location to the default value.
     */
    public static function resetConfigFileLocation(): void
    {
        static::$configFileLocation = null;
    }

    /**
     * Get the default config file location.
     *
     * @return string
     */
    public static function getDefaultConfigFileLocation(): string
    {
        return implode(DIRECTORY_SEPARATOR, [
            dirname(__FILE__),
            '..',
            '..',
            'config.php',
        ]);
    }

    /**
     * Get the SSH host.
     *
     * @return string|null
     */
    public function getHost(): ?string
    {
        return $this->config['host'] ?? null;
    }

    /**
     * Get the SSH port.
     *
     * @return int|null
     */
    public function getPort(): ?int
    {
        if ($port = $this->config['port']) {
            return (int)$port;
        }

        return null;
    }

    /**
     * Get the SSH key.
     *
     * @return string|null
     */
    public function getKey(): ?string
    {
        return $this->config['key'] ?? null;
    }

    /**
     * Get the SSH keyfile.
     *
     * @return string|null
     */
    public function getKeyfile(): ?string
    {
        return $this->config['keyfile'] ?? null;
    }

    /**
     * Get the SSH user.
     *
     * @return string|null
     */
    public function getUser(): ?string
    {
        return $this->config['user'] ?? null;
    }

    /**
     * Get the password used for password-based authentication or for unlocking
     * the key.
     *
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->config['password'] ?? null;
    }
}

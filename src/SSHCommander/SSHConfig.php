<?php /** @noinspection PhpIncludeInspection */

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Exceptions\ConfigFileMissingException;
use Neskodi\SSHCommander\Exceptions\ConfigValidationException;
use Neskodi\SSHCommander\Traits\ValidatesConnectionInfo;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use BadMethodCallException;

class SSHConfig implements SSHConfigInterface
{
    use ValidatesConnectionInfo;

    const CREDENTIAL_KEY      = 'key';
    const CREDENTIAL_KEYFILE  = 'keyfile';
    const CREDENTIAL_PASSWORD = 'password';

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
     * @param bool  $validateConnectionInfo Whether to require connection
     *                                      information such as host, user etc
     */
    public function __construct(
        array $config = [],
        bool $validateConnectionInfo = true
    ) {
        $this->loadDefaultConfigFile()
             ->loadUserConfigFile();

        // Now that the entire configuration is in place, we can validate
        // connection information if necessary.
        if ($validateConnectionInfo) {
            $this->validate($config);
        }

        $this->setFromArray($config);
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
             ->validateLoginCredential($config);

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
     * Verify that at least one of authentication credentials is present and in
     * good condition.
     *
     * @param array $config
     *
     * @return SSHConfigInterface
     */
    protected function validateLoginCredential(array $config): SSHConfigInterface
    {
        $credential = $this->selectCredential($config);

        switch ($credential) {
            case self::CREDENTIAL_KEY:
                $this->validateKey($config);
                break;
            case self::CREDENTIAL_KEYFILE:
                $this->validateKeyfile($config);
                break;
            case self::CREDENTIAL_PASSWORD:
                $this->validatePassword($config);
                break;
            default:
                throw new ConfigValidationException(
                    'No valid authentication credential is provided.'
                );
        }

        return $this;
    }

    /**
     * Select which credential will be used for authentication.
     * Priority order: key, key read from keyfile, password.
     *
     * @param array|null $config
     *
     * @return string|null
     */
    public function selectCredential(?array $config = null): ?string
    {
        $config = $config ?? $this->config;

        if (isset($config['key']) && !empty($config['key'])) {
            return self::CREDENTIAL_KEY;
        }

        if (isset($config['keyfile']) && !empty($config['keyfile'])) {
            return self::CREDENTIAL_KEYFILE;
        }

        if (isset($config['password'])) {
            return self::CREDENTIAL_PASSWORD;
        }

        return null;
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
            $this->validateBeforeSet($param, $value);

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

    /**
     * Validate the value before adding it to the config. Normal validation
     * rules apply.
     *
     * @param string $param
     * @param        $value
     */
    protected function validateBeforeSet(string $param, $value): void
    {
        $validationMethod = 'validate' . ucfirst(strtolower($param));
        if (method_exists($this, $validationMethod)) {
            $validatedArray = array_merge($this->all(), [$param => $value]);

            try {
                $this->$validationMethod($validatedArray);
            } catch (ConfigValidationException $e) {
                $message = 'Unable to add "%s" with value "%s" to config. ' .
                           $e->getMessage();
                $message = sprintf($message, $param, $value);
                throw new ConfigValidationException($message);
            }
        }
    }
}

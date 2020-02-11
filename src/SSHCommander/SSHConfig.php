<?php /** @noinspection PhpUnused */

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Exceptions\InvalidConfigValueException;
use Neskodi\SSHCommander\Exceptions\ConfigFileMissingException;
use Neskodi\SSHCommander\Exceptions\ConfigValidationException;
use Neskodi\SSHCommander\Traits\ValidatesConnectionInfo;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use InvalidArgumentException;
use BadMethodCallException;

class SSHConfig implements SSHConfigInterface
{
    use ValidatesConnectionInfo;

    const CREDENTIAL_KEY      = 'key';
    const CREDENTIAL_KEYFILE  = 'keyfile';
    const CREDENTIAL_PASSWORD = 'password';

    const BREAK_ON_ERROR_NEVER           = false;
    const BREAK_ON_ERROR_ALWAYS          = true;
    const BREAK_ON_ERROR_LAST_SUBCOMMAND = 'softfail';

    const TIMEOUT_CONDITION_RUNTIME = 'runtime';
    const TIMEOUT_CONDITION_NOOUT   = 'noout';

    const TIMEOUT_BEHAVIOR_TERMINATE              = "\x03"; // CTRL+C
    const TIMEOUT_BEHAVIOR_SUSPEND                = "\x1A"; // CTRL+Z
    const TIMEOUT_BEHAVIOR_CONTINUE_IN_BACKGROUND = "\x1Abg"; // CTRL+Z + 'bg'

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

    protected $defaultConfig = [];

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
     * @param bool  $skipValidation
     */
    public function __construct(
        array $config = []
    ) {
        $this->loadDefaultConfigFile()
             ->loadUserConfigFile();

        $this->set($config);
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

        // save the default config, to be able to restore default values at any
        // time
        $this->defaultConfig = $this->config;

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
            $this->set((array)include($file));
        }
    }

    /**
     * See if all data needed for authentication was provided. Do not throw an
     * exception, just return false.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        try {
            $this->validate();
        } catch (ConfigValidationException $exception) {
            return false;
        }

        return true;
    }

    /**
     * Validate the configuration and throw an Exception if something is wrong.
     *
     * This is normally done before establishing a connection.
     *
     * @throws ConfigValidationException
     */
    public function validate(): void
    {
        $this->validateHost($this->config)
             ->validatePort($this->config)
             ->validateUser($this->config)
             ->validateLoginCredential($this->config);
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
     * Set an arbitrary config parameter or a number of parameters.
     *
     * Ways to use:
     *
     * $config->set('key', $value);
     * $config->set(['key1' => $value1, 'key2' => $value2]);
     * $config->set($fromAnotherSSHConfigObject);
     *
     * @param string|array|SSHConfigInterface $param - string: the name of parameter to set, the value
     *                                               will be provided as second argument
     *                                               - array or SSHConfigInterface: a map of params and
     *                                               values to set
     * @param null|mixed                      $value if $param is a string (key to set), this is the value
     *                                               to set for that key. Otherwise this parameter is
     *                                               ignored.
     */
    public function set(
        $param,
        $value = null
    ): void {
        if (is_string($param)) {
            $this->setSingle($param, $value);

            return;
        }

        if ($param instanceof SSHConfigInterface) {
            $param = $param->all();
        }

        if (!is_array($param)) {
            throw new InvalidArgumentException(sprintf(
                'First parameter to SSHConfig::set() must be a string, an ' .
                'array or an SSHConfigInterface; %s given',
                gettype($param)
            ));
        }

        foreach ($param as $key => $value) {
            $this->setSingle($key, $value);
        }
    }

    /**
     * Set a single config parameter by its name.
     *
     * @param string $param
     * @param null   $value
     */
    protected function setSingle(string $param, $value = null): void
    {
        if (is_string($param)) {
            $method = 'set' . Utils::camelCase($param);
            if (method_exists($this, $method)) {
                // we have a special setter for this
                $this->$method($value);
            } else {
                $this->config[$param] = $value;
            }
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

    public function getDefault(string $param)
    {
        return array_key_exists($param, $this->defaultConfig)
            ? $this->defaultConfig[$param]
            : null;
    }

    /**
     * This method is here mainly to intercept calls to getters such as
     * "getBreakOnError()"
     *
     * @param string $name      the parameter to get
     * @param array  $arguments arguments as array
     *
     * @return mixed|null
     */
    public function __call(string $name, array $arguments = [])
    {
        if (0 === strpos($name, 'get')) {
            $param   = Utils::snakeCase(substr($name, 3));
            $default = $arguments[0] ?? null;

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
     * Check if a parameter is already set in the config array.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->config);
    }

    /**
     * Set the location of the config file that is used for reading the default
     * settings.
     *
     * @param string $location
     */
    public static function setUserConfigFileLocation(string $location): void
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
            'etc',
            'config.php',
        ]);
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
     * Get private key contents from the config object. May be stored directly
     * under 'key' or in the file pointed to by 'keyfile'.
     *
     * @return string|null
     */
    public function getKeyContents(): ?string
    {
        $keyContents = null;

        if ($key = $this->getKey()) {
            if (!is_scalar($key)) {
                throw new InvalidConfigValueException(sprintf(
                    'Invalid SSH key provided (string expected, got %s)',
                    gettype($key)
                ));
            }

            $keyContents = (string)$key;
        } elseif ($keyfile = $this->getKeyfile()) {
            $keyContents = (string)file_get_contents($keyfile);
        }

        return $keyContents;
    }
}

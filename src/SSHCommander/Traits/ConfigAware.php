<?php /** @noinspection PhpDocMissingReturnTagInspection */

namespace Neskodi\SSHCommander\Traits;

use Neskodi\SSHCommander\Exceptions\InvalidConfigException;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\SSHConfig;

trait ConfigAware
{
    /**
     * @var SSHConfigInterface
     */
    protected $config;

    /**
     * Fluent setter for SSHConfig object.
     *
     * @param array|SSHConfigInterface $config
     *
     * @return ConfigAware
     */
    public function setConfig($config)
    {
        $configObject = $this->toConfigObject($config);

        $this->config = $configObject;

        return $this;
    }

    /**
     * Merge the new config values into the existing config object.
     *
     * @param array|SSHConfigInterface $config
     *
     * @param bool                     $missingOnly if true, skip options that
     *                                              are already present in the
     *                                              original array and only add
     *                                              missing ones
     *
     * @return $this
     */
    public function mergeConfig($config, bool $missingOnly = false)
    {
        $options = $this->toConfigArray($config);

        foreach ($options as $key => $value) {
            if ($missingOnly && $this->config->has($key)) {
                continue;
            }

            $this->config->set($key, $value, $this->skipConfigValidation(), $options);
        }

        return $this;
    }

    /**
     * Set a single option.
     *
     * @param string $key
     * @param        $value
     */
    public function setOption(string $key, $value)
    {
       $this->config->set($key, $value);

       return $this;
    }

    /**
     * Get the SSHConfig object used by this connection, or a specific key from
     * that config object.
     *
     * @param string|null $param
     *
     * @return string|SSHConfigInterface
     */
    public function getConfig(?string $param = null)
    {
        return (is_string($param) && $this->config instanceof SSHConfigInterface)
            ? $this->config->get($param)
            : $this->config;
    }

    /**
     * If a new instance of SSHConfig is created by setConfig,
     * tell us whether it needs to validate connection information
     * (host, username, etd) for this particular class.
     *
     * @return bool
     */
    protected function skipConfigValidation(): bool
    {
        return false;
    }

    /**
     * Given an array or an SSHConfigInterface object, return the object.
     *
     * @param array|SSHConfigInterface $config
     *
     * @return SSHConfigInterface|SSHConfig
     */
    protected function toConfigObject($config): SSHConfigInterface
    {
        if (is_array($config)) {
            $configObject = new SSHConfig(
                $config,
                $this->skipConfigValidation()
            );
        } elseif ($config instanceof SSHConfigInterface) {
            $configObject = $config;
        } else {
            throw new InvalidConfigException(gettype($config));
        }

        return $configObject;
    }

    /**
     * Given an array or an SSHConfigInterface object, return the array.
     *
     * @param array|SSHConfigInterface $config
     *
     * @return array
     */
    protected function toConfigArray($config): array
    {
        return $this->toConfigObject($config)->all();
    }
}

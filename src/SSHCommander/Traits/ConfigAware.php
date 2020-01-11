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
     * Set a single option or a number of options.
     *
     * See the documentation for SSHConfig::set()
     *
     * @param string|array|SSHConfigInterface $param
     * @param mixed                           $value
     *
     * @return ConfigAware
     */
    public function set($param, $value = null)
    {
        $this->config->set($param, $value);

        return $this;
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
            $configObject = new SSHConfig($config);
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

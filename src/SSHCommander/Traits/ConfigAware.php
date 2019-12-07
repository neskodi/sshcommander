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
     * @param bool                     $validateForConnection
     *
     * @return ConfigAware
     */
    public function setConfig($config, bool $validateForConnection = true)
    {
        if (is_array($config)) {
            $configObject = new SSHConfig($config, $validateForConnection);
        } elseif ($config instanceof SSHConfigInterface) {
            $configObject = $config;
        } else {
            throw new InvalidConfigException(gettype($config));
        }

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
}
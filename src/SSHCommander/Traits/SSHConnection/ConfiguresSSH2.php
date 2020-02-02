<?php /** @noinspection PhpIncompatibleReturnTypeInspection */

/** @noinspection PhpUnused */

namespace Neskodi\SSHCommander\Traits\SSHConnection;

use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use phpseclib\Net\SSH2;

trait ConfiguresSSH2
{
    abstract public function getSSH2(): SSH2;

    /**
     * Set the timeout (in seconds) for the next SSH2 operation.
     *
     * @param int $timeout timeout in seconds
     *
     * @return $this
     */
    public function setTimeout(int $timeout): SSHConnectionInterface
    {
        $this->getSSH2()->setTimeout($timeout);

        return $this;
    }

    /**
     * Reset the timeout to its default value.
     *
     * @return $this
     */
    public function resetTimeout(): SSHConnectionInterface
    {
        $this->setTimeout($this->getConfig()->getDefault('timeout'));

        return $this;
    }

    /**
     * Enable quiet mode on the SSH2 object.
     *
     * @return SSHConnectionInterface
     */
    public function enableQuietMode(): SSHConnectionInterface
    {
        $this->getSSH2()->enableQuietMode();

        return $this;
    }

    /**
     * By default, quiet mode is disabled in phpseclib. Return it to that state.
     *
     * @return $this
     */
    public function resetQuietMode(): SSHConnectionInterface
    {
        $this->getSSH2()->disableQuietMode();

        return $this;
    }

    /**
     * Reset all parameters of the SSH2 object to their default values.
     *
     * @return SSHConnectionInterface
     */
    public function resetSSH2Configuration(): SSHConnectionInterface
    {
        $this->resetQuietMode()
             ->resetTimeout();

        return $this;
    }
}

<?php

namespace Neskodi\SSHCommander\Traits;

use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;

trait HasConnection
{
    /**
     * @var SSHConnectionInterface
     */
    protected $connection;

    /**
     * Get the SSH Connection instance used by this object.
     *
     * @return null|SSHConnectionInterface
     */
    public function getConnection(): ?SSHConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Set the connection to run the command on.
     *
     * @param SSHConnectionInterface $connection
     *
     * @return $this
     *
     * @noinspection PhpUnused
     */
    public function setConnection(SSHConnectionInterface $connection)
    {
        $this->connection = $connection;

        return $this;
    }

}

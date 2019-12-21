<?php

namespace Neskodi\SSHCommander\Traits;

use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;

trait HasResult
{
    /**
     * @var SSHCommandResultInterface
     */
    protected $result;

    /**
     * Get the result of the last command run.
     *
     * @return null|SSHCommandResultInterface
     */
    public function getResult(): ?SSHCommandResultInterface
    {
        return $this->result;
    }

    /**
     * Set the result object to be stored in this object.
     *
     * @param SSHCommandResultInterface $result
     *
     * @return $this
     */
    public function setResult(SSHCommandResultInterface $result)
    {
        $this->result = $result;

        return $this;
    }
}

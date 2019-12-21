<?php

namespace Neskodi\SSHCommander\Traits;

use Neskodi\SSHCommander\Interfaces\SSHResultCollectionInterface;
use Neskodi\SSHCommander\SSHResultCollection;

trait HasResultCollection
{
    /**
     * @var SSHResultCollectionInterface
     */
    protected $resultCollection;

    /**
     * Get the instance of result collection.
     *
     * @return SSHResultCollectionInterface
     */
    public function getResultCollection(): ?SSHResultCollectionInterface
    {
        if (!$this->resultCollection) {
            $this->resultCollection = new SSHResultCollection;
        }

        return $this->resultCollection;
    }
}

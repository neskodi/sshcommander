<?php

namespace Neskodi\SSHCommander\Traits;

use Neskodi\SSHCommander\Interfaces\OutputProcessorInterface;
use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;

trait HasOutputProcessor
{
    /**
     * @var OutputProcessorInterface
     */
    protected $output;

    /**
     * Get the SSH Connection instance used by this object.
     *
     * @return null|SSHConnectionInterface
     * @noinspection PhpUnused
     */
    public function getOutputProcessor(): ?OutputProcessorInterface
    {
        return $this->output;
    }

    /**
     * Set the connection to run the command on.
     *
     * @param OutputProcessorInterface $processor
     *
     * @return $this
     * @noinspection PhpUnused
     */
    public function setOutputProcessor(OutputProcessorInterface $processor)
    {
        $this->output = $processor;

        return $this;
    }
}

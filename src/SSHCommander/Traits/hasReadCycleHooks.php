<?php

namespace Neskodi\SSHCommander\Traits;

trait hasReadCycleHooks
{
    protected $readCycleHooks = [];

    /**
     * Add a function that will be run on each iteration of
     *
     * @param callable $hook
     */
    public function addReadCycleHook(callable $hook): void
    {
        $this->readCycleHooks[] = $hook;
    }

    /**
     * Get the array of read cycle hook functions
     *
     * @return array
     */
    public function getReadCycleHooks(): array
    {
        return $this->readCycleHooks;
    }
}

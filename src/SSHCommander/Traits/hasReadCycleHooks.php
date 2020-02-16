<?php

namespace Neskodi\SSHCommander\Traits;

trait hasReadCycleHooks
{
    protected $readCycleHooks = [];

    /**
     * Add a function that will be run on each iteration of the reading cycle.
     *
     * @param callable    $hook
     * @param string|null $name
     */
    public function addReadCycleHook(callable $hook, ?string $name = null): void
    {
        if (!empty($name)) {
            $this->readCycleHooks[$name] = $hook;
        } else {
            $this->readCycleHooks[] = $hook;
        }
    }

    public function deleteReadCycleHook(string $name): void
    {
        if (array_search($name, $this->readCycleHooks)) {
            unset($this->readCycleHooks[$name]);
        }
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

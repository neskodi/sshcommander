<?php

namespace Neskodi\SSHCommander\Traits;

trait HasReadCycleHooks
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

    /**
     * Remove a named hook by its name.
     *
     * @param string $name
     */
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

    /**
     * Set or replace all read cycle hooks used by this object.
     *
     * @param array $hooks
     */
    public function setReadCycleHooks(array $hooks = []): void
    {
        $this->clearReadCycleHooks();

        foreach ($hooks as $name => $hook) {
            $this->addReadCycleHook($hook, $name);
        }
    }

    /**
     * SSH Connection registers a number of hook functions to be
     * executed after each stream_select iteration (by default this happens
     * every 0.5 seconds while connection is waiting for output. If the command
     * produces output earlier, the hooks will be executed immediately upon output.
     *
     * CRTimeoutDecorator and other decorators register their own logic as hooks.
     * You may also register your own watcher to run per iteration, by calling
     * addReadIterationHook(). Your hook, when called, will receive $connection
     * (this object) and $stepOutput (new output that became available on the
     * channel since last reading, if any, an empty string otherwise) as arguments.
     * If you return a truthy value, the read cycle will stop and control
     * will be returned to your program.
     *
     * Please note that if you break the normal flow of command run,
     * it's your responsibility to stop the command e.g. by calling
     * $connection->terminateCommand(), and clean up channel artifacts via e.g.
     * $connection->getSSH2()->read() in your hook function.
     *
     * @param string $stepOutput the new (portion of) output returned by
     *                           the command on this step
     *
     * @return bool
     */
    public function runReadCycleHooks(string $stepOutput = ''): bool
    {
        $shouldBreak = false;

        foreach ($this->getReadCycleHooks() as $hook) {
            if ($hook($this, $stepOutput)) {
                $shouldBreak = true;
            }
        }

        return $shouldBreak;
    }

    /**
     * Remove all read cycle hooks.
     */
    public function clearReadCycleHooks(): void
    {
        $this->readCycleHooks = [];
    }
}

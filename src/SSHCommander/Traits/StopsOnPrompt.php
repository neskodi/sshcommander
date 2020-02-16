<?php

namespace Neskodi\SSHCommander\Traits;

/**
 * Trait StopsOnPrompt
 *
 * Depends on hasReadCycleHooks
 */
trait StopsOnPrompt
{
    abstract public function addReadCycleHook(callable $hook, ?string $name = null): void;

    abstract public function deleteReadCycleHook(string $name): void;

    abstract function getConfig(?string $param = null);

    /**
     * Create a read cycle hook that will make SSH2 stop reading as soon as the
     * prompt is detected in output.
     *
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpInconsistentReturnPointsInspection
     *
     * @param bool $flag
     */
    public function stopsOnPrompt(bool $flag = true): void
    {
        $regex = $this->getConfig()->getPromptRegex();

        if ($flag && $regex) {
            $this->addReadCycleHook(function ($conn, $newOutput) use ($regex) {
                if (preg_match($regex, $newOutput)) {
                    return true;
                }
            }, 'prompt');
        } elseif (!$flag) {
            $this->deleteReadCycleHook('detect_prompt');
        }
    }
}

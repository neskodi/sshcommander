<?php

namespace Neskodi\SSHCommander\Traits;

use Neskodi\SSHCommander\SSHConfig;

trait SetsConfigValues
{
    /**
     * Tell SSHCommander whether to throw exception whenever a command returns
     * a non-zero exit code.
     *
     * SSHConfig::BREAK_ON_ERROR_ALWAYS (or just true) - break always and reset the connection.
     * SSHConfig::BREAK_ON_ERROR_NEVER (or just false) - never break and keep the connection.
     * SSHConfig::BREAK_ON_ERROR_LAST_SUBCOMMAND - break only if the last (or single) subcommand in a compond command
     * exits with a non-zero code.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function breakOnError($value = SSHConfig::BREAK_ON_ERROR_ALWAYS)
    {
        $this->set('break_on_error', $value);

        return $this;
    }

    /**
     * Set the timeout and optionally also the timeout action.
     *
     * Timeout defines how long commands should wait for response packets.
     *
     * @param int               $value    value for the timeout, in seconds
     * @param null|string|false $behavior if false, this argument is ignored.
     *                                    Otherwise it will be set to whatever
     *                                    is provided. Passing null will clear
     *                                    any timeout action.
     *
     * @return $this
     */
    public function timeout(int $value = 10, $behavior = false)
    {
        $this->set('timeout_command', $value);
        $this->set('timeout_connect', $value);

        if (is_string($behavior) || is_null($behavior)) {
            $this->set('timeout_behavior', $behavior);
        }

        return $this;
    }

    /**
     * Set the timelimit and optionally also the timelimit action.
     *
     * Timelimit defines for how long commands are allowed to run.
     *
     * @param int               $value    value for the timelimit, in seconds
     * @param null|string|false $behavior if false, this argument is ignored.
     *                                    Otherwise it will be set to whatever
     *                                    is provided. Passing null will clear
     *                                    any timelimit action.
     *
     * @return $this
     */
    public function timelimit(int $value = 10, $behavior = false)
    {
        $this->set('timelimit', $value);

        if (is_string($behavior) || is_null($behavior)) {
            $this->set('timelimit_behavior', $behavior);
        }

        return $this;
    }
}

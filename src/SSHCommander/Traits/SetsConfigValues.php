<?php

namespace Neskodi\SSHCommander\Traits;

use Neskodi\SSHCommander\Exceptions\InvalidConfigValueException;
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
     * @noinspection PhpUnused
     */
    public function breakOnError($value = SSHConfig::BREAK_ON_ERROR_ALWAYS)
    {
        $this->ensureValueInArray('break_on_error', $value, [
            SSHConfig::BREAK_ON_ERROR_ALWAYS,
            SSHConfig::BREAK_ON_ERROR_NEVER,
            SSHConfig::BREAK_ON_ERROR_LAST_SUBCOMMAND,
        ]);


        $this->set('break_on_error', $value);

        return $this;
    }

    /**
     * Set the current working directory on the fly so that all commands running
     * afterwards will use this directory.
     *
     * @param string $value
     *
     * @return $this
     */
    public function basedir(string $value)
    {
        if (empty($value)) {
            throw new InvalidConfigValueException('Basedir cannot be empty');
        }

        $this->set('basedir', $value);

        return $this;
    }

    /**
     * A shortcut to set the timeout, timeout_condition and timeout_behavior
     * in one call.
     *
     * @param int               $value     value for the timeout, in seconds
     * @param null|string       $condition whether to limit the general running
     *                                     time of the command, or the time when
     *                                     no more packets are coming.
     *                                     Possible values:
     *                                     - SSHConfig::TIMEOUT_CONDITION_RUNNING_TIMELIMIT
     *                                     - SSHConfig::TIMEOUT_CONDITION_READING_TIMEOUT
     *                                     - null (argument is ignored)
     * @param null|string|false $behavior  Whether to send anything to the channel
     *                                     after a timeout is detected.
     *                                     Possible values:
     *                                     - false: clear the previously configured behavior
     *                                     (don't do anything on timeout)
     *                                     - null: ignore this argument
     *                                     (keep the previously configured behavior)
     *                                     - (any string) - send these characters
     *                                     when a timeout is detected
     *
     * @return $this
     */
    public function timeout(
        int $value = 10,
        ?string $condition = null,
        $behavior = null
    ) {
        // set the timeout value in seconds
        $this->setTimeoutValueOnTheFly($value);

        // set the condition
        if (!is_null($condition)) {
            $this->setTimeoutConditionOnTheFly($condition);
        }

        // set the behavior
        if (!is_null($behavior)) {
            $this->setTimeoutBehaviorOnTheFly($behavior);
        }

        return $this;
    }

    /**
     * Throw an exception if the passed value is not among the expected ones.
     *
     * @param string $name        name to use in Exception
     * @param mixed  $value       the passed value
     * @param array  $validValues the array of valid values
     */
    protected function ensureValueInArray(string $name, $value, array $validValues): void
    {
        $strValidValues = implode(
            ',',
            array_map(function ($v) {
                return "'$v'";
            }, $validValues)
        );

        if (!in_array($value, $validValues)) {
            throw new InvalidConfigValueException(sprintf(
                'Invalid value for %s: %s (must be one of %s)',
                $name,
                $value,
                $strValidValues
            ));
        }
    }

    /**
     * Set the timeout value on the fly.
     *
     * @param int $value
     */
    protected function setTimeoutValueOnTheFly(int $value): void
    {
        $this->set('timeout', $value);
        $this->set('timeout_connect', $value);
    }

    /**
     * Set the timeout condition on the fly
     *
     * @param string $condition
     */
    protected function setTimeoutConditionOnTheFly(string $condition): void
    {
        $this->ensureValueInArray('timeout condition', $condition, [
            SSHConfig::TIMEOUT_CONDITION_RUNNING_TIMELIMIT,
            SSHConfig::TIMEOUT_CONDITION_READING_TIMEOUT,
        ]);

        $this->set('timeout_condition', $condition);
    }

    /**
     * Set the timeout behavior on the fly.
     *
     * @param $behavior
     */
    protected function setTimeoutBehaviorOnTheFly($behavior): void
    {
        if (false === $behavior) {
            // clear any behavior that might be set previously
            $this->set('timeout_behavior', null);
        } elseif (is_string($behavior)) {
            // set the behavior to the desired character sequence
            $this->set('timeout_behavior', $behavior);
        } elseif (is_callable($behavior)) {
            $this->set('timeout_behavior', $behavior);
        } else {
            // anything else is invalid
            throw new InvalidConfigValueException(sprintf(
                'Invalid value for timeout behavior: %s',
                gettype($behavior)
            ));
        }
    }
}

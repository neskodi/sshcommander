<?php

namespace Neskodi\SSHCommander\Traits\SSHConnection;

use Neskodi\SSHCommander\SSHConfig;
use Neskodi\SSHCommander\VendorOverrides\phpseclib\Net\SSH2;

trait ControlsCommandTime
{
    /** @var bool */
    protected $isTimeout = false;

    /** @var bool */
    protected $isTimelimit = false;

    abstract public function getSSH2(): SSH2;

    abstract public function getConfig(?string $key = null);

    /**
     * Return the time since last packet was received
     *
     * @return float
     */
    public function timeSinceLastResponse(): float
    {
        $lastPacketTime = $this->getSSH2()->getLastResponseTime();

        // if no packet was yet received, we count from the time when command
        // started running
        if (!is_float($lastPacketTime)) {
            return $this->timeSinceCommandStart();
        }

        // return the actual time since last packet
        return microtime(true) - $lastPacketTime;
    }

    /**
     * Return the time since the current command started running.
     *
     * @return float
     */
    public function timeSinceCommandStart(): float
    {
        return microtime(true) - $this->getTimerStart();
    }

    /**
     * Check if either 'timeout' or 'timelimit' condition has been reached
     * while running this command.
     *
     * @return bool
     */
    public function isTimeout(): bool
    {
        return (bool)$this->getSSH2()->isTimeout() || $this->isTimeout;
    }

    /**
     * Check if the 'timelimit' timeout condition has specifically been reached
     * while running this command.
     *
     * @return bool
     */
    public function isTimelimit(): bool
    {
        return $this->isTimelimit;
    }

    /**
     * @param bool $isTimeout
     *
     * @return $this
     */
    public function setIsTimeout(bool $isTimeout)
    {
        $this->isTimeout = $isTimeout;

        return $this;
    }

    /**
     * @param bool $isTimelimit
     *
     * @return $this
     */
    public function setIsTimelimit(bool $isTimelimit)
    {
        $this->isTimelimit = $isTimelimit;

        return $this;
    }

    /**
     * Clear the timeout status of possible previous commands.
     *
     * @return $this
     */
    protected function resetTimeoutStatus()
    {
        $this->isTimeout   = false;
        $this->isTimelimit = false;

        return $this;
    }

    /**
     * If user has set the timeout condition to be 'timelimit' and the command is
     * already running longer than specified by the 'timeout' config value,
     * return true.
     *
     * @return bool
     */
    public function reachedTimeLimit(): bool
    {
        $timeout               = $this->getConfig('timeout');
        $condition             = $this->getConfig('timeout_condition');
        $timeSinceCommandStart = $this->timeSinceCommandStart();

        $result = (
            // user wants to timeout by 'timelimit'
            (SSHConfig::TIMEOUT_CONDITION_RUNNING_TIMELIMIT === $condition) &&
            // user has set a non-zero timeout value
            $timeout &&
            // and this time has passed since the command started
            ($timeSinceCommandStart >= $timeout)
        );

        if ($result) {
            $this->isTimelimit = true;
        }

        return $result;
    }

    /**
     * If user has set the timeout condition to be 'timeout' and we have been waiting
     * for output  already longer than specified by the 'timeout' config value,
     * return true.
     *
     * @return bool
     */
    public function reachedTimeout(): bool
    {
        $timeout             = $this->getConfig('timeout');
        $condition           = $this->getConfig('timeout_condition');
        $timeSinceLastPacket = $this->timeSinceLastResponse();

        return (
            // user wants to timeout by 'READING_TIMEOUT'
            (SSHConfig::TIMEOUT_CONDITION_READING_TIMEOUT === $condition) &&
            // user has set a non-zero timeout value
            $timeout &&
            // and this time has passed since the last packet was received
            ($timeSinceLastPacket >= $timeout)
        );
    }
}

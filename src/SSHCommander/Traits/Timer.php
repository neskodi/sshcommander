<?php

namespace Neskodi\SSHCommander\Traits;

trait Timer
{
    /**
     * @var float
     */
    protected $timerStart;

    /**
     * @var float
     */
    protected $timerEnd;

    /**
     * @var int
     */
    protected $precision = 5;

    /**
     * Start the timer.
     */
    public function startTimer(): void
    {
        $this->timerStart = microtime(true);
    }

    /**
     * End the timer and return elapsed time.
     *
     * @return float
     */
    public function stopTimer(): float
    {
        $this->timerEnd = microtime(true);
        $total          = $this->timerEnd - $this->timerStart;

        $this->resetTimer();

        return round($total, $this->precision);
    }

    /**
     * Reset the timer.
     */
    public function resetTimer(): void
    {
        $this->timerStart = $this->timerEnd = null;
    }

    /**
     * Set timer precision.
     *
     * @param int $precision
     */
    public function setTimerPrecision(int $precision): void
    {
        $this->precision = $precision;
    }
}

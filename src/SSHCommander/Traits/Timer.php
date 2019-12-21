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

        return $this->getElapsedTime();
    }

    /**
     * Get the time difference betweet timer start and end, with microseconds.
     *
     * @return float
     */
    public function getElapsedTime(): float
    {
        return round(
            $this->timerEnd - $this->timerStart,
            $this->precision
        );
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

    /**
     * @return null|float
     */
    public function getTimerStart(): ?float
    {
        return $this->timerStart;
    }

    /**
     * @return null|float
     */
    public function getTimerEnd(): ?float
    {
        return $this->timerEnd;
    }
}

<?php

namespace Neskodi\SSHCommander\Interfaces;

interface TimerInterface
{
    public function startTimer(): void;

    public function stopTimer(): float;

    public function resetTimer(): void;

    public function setTimerPrecision(int $precision): void;
}

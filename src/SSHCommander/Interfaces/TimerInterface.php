<?php

namespace Neskodi\SSHCommander\Interfaces;

interface TimerInterface
{
    public function startTimer(): void;

    public function endTimer(): float;

    public function resetTimer(): void;

    public function setTimerPrecision(int $precision): void;
}

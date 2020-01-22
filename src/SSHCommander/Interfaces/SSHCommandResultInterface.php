<?php

namespace Neskodi\SSHCommander\Interfaces;

interface SSHCommandResultInterface
{
    public function setExitCode(?int $code = null): SSHCommandResultInterface;

    public function setOutput(array $lines): SSHCommandResultInterface;

    public function setErrorOutput(array $lines): SSHCommandResultInterface;

    public function getStatus(): ?string;

    public function isOk(): ?bool;

    public function isError(): ?bool;

    public function getExitCode(): ?int;

    public function getOutput(bool $asString = false);

    public function getErrorOutput(bool $asString = false);

    public function __toString(): string;

    public function setOutputDelimiter(string $delimiter): SSHCommandResultInterface;

    public function setCommand(SSHCommandInterface $command): SSHCommandResultInterface;

    public function getCommand(): SSHCommandInterface;

    public function logResult(): void;

    public function setCommandStartTime(float $time): SSHCommandResultInterface;

    public function setCommandEndTime(float $time): SSHCommandResultInterface;

    public function setCommandElapsedTime(float $time): SSHCommandResultInterface;

    public function getCommandStartTime(): ?float;

    public function getCommandEndTime(): ?float;

    public function getCommandElapsedTime(): ?float;

    public function setIsTimeout(bool $isTimeout): SSHCommandResultInterface;

    public function setIsTimelimit(bool $isTimeout): SSHCommandResultInterface;

    public function isTimeout(): ?bool;

    public function isTimelimit(): ?bool;
}

<?php

namespace Neskodi\SSHCommander\Interfaces;

interface SSHCommandResultInterface
{
    public function setExitCode(int $code): SSHCommandResultInterface;

    public function setOutput(array $lines): SSHCommandResultInterface;

    public function setErrorOutput(array $lines): SSHCommandResultInterface;

    public function getStatus(): string;

    public function isOk(): bool;

    public function isError(): bool;

    public function getExitCode(): int;

    public function getOutput(bool $asString = false);

    public function getErrorOutput(bool $asString = false);

    public function __toString(): string;

    public function setOutputDelimiter(string $delimiter): SSHCommandResultInterface;

    public function setCommand(SSHCommandInterface $command): SSHCommandResultInterface;

    public function getCommand(): SSHCommandInterface;

    public function logResult(): void;
}

<?php

namespace Neskodi\SSHCommander\Interfaces;

interface CommandResultInterface
{
    public function setExitCode(int $code): CommandResultInterface;

    public function setOutput(array $output): CommandResultInterface;

    public function getStatus(): string;

    public function isOk(): bool;

    public function isError(): bool;

    public function getExitCode(): int;

    public function getOutput(bool $asString = false);

    public function __toString(): string;

    public function setOutputDelimiter(string $delimiter): CommandResultInterface;

    public function setCommand(CommandInterface $command): CommandResultInterface;

    public function getCommand(): CommandInterface;
}

<?php

namespace Neskodi\SSHCommander\Interfaces;

interface CommandInterface
{
    public function setCommand(string $command): CommandInterface;

    public function getCommands(bool $asString = true);

    public function setOptions(array $options = []): CommandInterface;

    public function setOption(string $key, $value): CommandInterface;

    public function getOptions(): array;

    public function getOption(string $key);

    public function breaksOnError(): bool;
}

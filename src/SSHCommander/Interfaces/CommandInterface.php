<?php

namespace Neskodi\SSHCommander\Interfaces;

interface CommandInterface
{
    public function setCommand(string $command): CommandInterface;

    public function getCommands(bool $asString = true, bool $prepared = true);

    public function setOptions(array $options = []): CommandInterface;

    public function setOption(string $key, $value): CommandInterface;

    public function getOptions(): array;

    public function getOption(string $key);

    public function appendCommand($command): CommandInterface;

    public function prependCommand($command): CommandInterface;
}

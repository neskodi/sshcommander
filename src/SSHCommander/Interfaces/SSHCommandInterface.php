<?php

namespace Neskodi\SSHCommander\Interfaces;

interface SSHCommandInterface
{
    public function setCommand($command): SSHCommandInterface;

    public function getCommands(bool $asString = true, bool $prepared = true);

    public function setOptions(array $options = []): SSHCommandInterface;

    public function setOption(string $key, $value): SSHCommandInterface;

    public function getOptions(): array;

    public function getOption(string $key);

    public function appendCommand($command): SSHCommandInterface;

    public function prependCommand($command): SSHCommandInterface;

    public function toLoggableString($delimiter = ';'): string;
}

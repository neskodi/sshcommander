<?php

namespace Neskodi\SSHCommander\Interfaces;

interface SSHCommandInterface
{
    public function setCommand($command): SSHCommandInterface;

    public function getCommands(bool $asString = true, bool $prepared = true);

    public function setOption(string $key, $value): SSHCommandInterface;

    public function setOptions(array $options = []): SSHCommandInterface;

    public function appendCommand($command): SSHCommandInterface;

    public function prependCommand($command): SSHCommandInterface;

    public function toLoggableString($delimiter = ';'): string;
}

<?php

namespace Neskodi\SSHCommander\Interfaces;

interface SSHCommandInterface
{
    public function setConfig(SSHConfigInterface $config);

    public function getConfig(?string $param = null);

    public function setCommand($command): SSHCommandInterface;

    public function getCommands(bool $asString = true, bool $prepared = true);

    public function setOption(string $key, $value): SSHCommandInterface;

    public function setOptions(array $options = [], bool $soft = false): SSHCommandInterface;

    public function appendCommand($command): SSHCommandInterface;

    public function prependCommand($command): SSHCommandInterface;

    public function toLoggableString($delimiter = ';'): string;
}

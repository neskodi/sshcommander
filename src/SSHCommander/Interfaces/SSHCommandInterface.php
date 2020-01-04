<?php

namespace Neskodi\SSHCommander\Interfaces;

interface SSHCommandInterface
{
    public function setConfig(SSHConfigInterface $config);

    public function getConfig(?string $param = null);

    public function setCommand($command): SSHCommandInterface;

    public function getCommand(): string;

    public function setOption(string $key, $value): SSHCommandInterface;

    public function mergeConfig($options, bool $missingOnly = false): SSHCommandInterface;

    public function appendCommand($command): SSHCommandInterface;

    public function prependCommand($command): SSHCommandInterface;

    public function toLoggableString(): string;
}

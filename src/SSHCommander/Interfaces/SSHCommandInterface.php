<?php

namespace Neskodi\SSHCommander\Interfaces;

interface SSHCommandInterface
{
    public function setConfig(SSHConfigInterface $config);

    public function getConfig(?string $param = null);

    public function set($param, $value = null);

    public function setCommand($command): SSHCommandInterface;

    public function getCommand(): string;

    public function appendCommand($command): SSHCommandInterface;

    public function prependCommand($command): SSHCommandInterface;

    public function wrap(string $pattern): SSHCommandInterface;

    public function toLoggableString(): string;

    public function getSuccessfulExitCodes(): array;

    public function addReadCycleHook(callable $hook): void;

    public function getReadCycleHooks(): array;
}

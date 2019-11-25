<?php

namespace Neskodi\SSHCommander\Interfaces;

interface SSHConfigInterface
{
    public function get(string $name, $default = null);

    public function set(string $name, $value);

    public function all(): array;

    public function isLocal(): bool;

    public function validate(array $config): void;
}

<?php

namespace Neskodi\SSHCommander\Interfaces;

interface SSHConfigInterface
{
    public function get(string $name, $default = null);

    public function set($param, $value);

    public function all(): array;

    public function has(string $key): bool;

    public function validate(): void;

    public function isValid(): bool;

    public function getKeyContents(): ?string;

    public static function setConfigFileLocation(string $location): void;

    public static function getConfigFileLocation(): ?string;

    public static function getDefaultConfigFileLocation(): string;

    public function selectCredential(?array $config = null): ?string;
}

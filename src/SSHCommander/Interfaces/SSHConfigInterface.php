<?php

namespace Neskodi\SSHCommander\Interfaces;

interface SSHConfigInterface
{
    public function get(string $name, $default = null);

    public function set(string $name, $value);

    public function setFromArray(array $config): SSHConfigInterface;

    public function all(): array;

    public function has(string $key): bool;

    public function validate(array $config): SSHConfigInterface;

    public function getHost(): ?string;

    public function getPort(): ?int;

    public function getKey(): ?string;

    public function getKeyfile(): ?string;

    public function getUser(): ?string;

    public function getPassword(): ?string;

    public static function setConfigFileLocation(string $location): void;

    public static function getConfigFileLocation(): ?string;

    public static function getDefaultConfigFileLocation(): string;

    public function selectCredential(?array $config = null): ?string;
}

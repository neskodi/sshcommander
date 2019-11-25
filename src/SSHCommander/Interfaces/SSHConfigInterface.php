<?php

namespace Neskodi\SSHCommander\Interfaces;

interface SSHConfigInterface
{
    public function get(string $name, $default = null);

    public function set(string $name, $value);

    public function all(): array;

    public function isLocal(): bool;

    public function validate(array $config): void;

    public function getHost(): ?string;

    public function getPort(): ?int;

    public function getKey(): ?string;

    public function getKeyfile(): ?string;

    public function getUser(): ?string;

    public function getPassword(): ?string;

    public function getLocalAddresses(): ?array;

    public static function setConfigFileLocation(string $location): void;

    public static function getConfigFileLocation(): ?string;
}

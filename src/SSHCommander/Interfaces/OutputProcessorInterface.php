<?php

namespace Neskodi\SSHCommander\Interfaces;

interface OutputProcessorInterface
{
    public function add(string $output): void;

    public function addErr(string $output): void;

    public function get(bool $clean = true): array;

    public function getErr(bool $clean = true): array;

    public function getAsString(bool $clean = true): string;

    public function getErrAsString(bool $clean = true): string;

    public function getRaw(): string;

    public function getRawErr(): string;

    public function hasPrompt(?string $string = null): bool;

    public function hasMarker(string $markerRegex): bool;
}

<?php

namespace Neskodi\SSHCommander\Interfaces;

use ArrayAccess;

interface SSHResultCollectionInterface extends ArrayAccess
{
    public function all(): array;

    public function isOk(): bool;

    public function isError(): bool;

    public function successful(): SSHResultCollectionInterface;

    public function hasSuccessfulResults(): bool;

    public function countSuccessfulResults(): int;

    public function failed(): SSHResultCollectionInterface;

    public function hasFailedResults(): bool;

    public function countFailedResults(): int;

    public function hasResultsThatMatch(string $pattern, ?string $mode = null): bool;

    public function hasResultsThatContain(string $pattern, ?string $mode = null): bool;

    public function getResultsThatMatch(string $pattern, ?string $mode = null): SSHResultCollectionInterface;

    public function getResultsThatContain(string $pattern, ?string $mode = null): SSHResultCollectionInterface;

    public function getFirstResultThatMatches(string $pattern, ?string $mode = null): ?SSHCommandResultInterface;

    public function getLastResultThatMatches(string $pattern, ?string $mode = null): ?SSHCommandResultInterface;

    public function getFirstResultThatContains(string $pattern, ?string $mode = null): ?SSHCommandResultInterface;

    public function getLastResultThatContains(string $pattern, ?string $mode = null): ?SSHCommandResultInterface;

    public function getFirstFailedResult(): ?SSHCommandResultInterface;

    public function getLastFailedResult(): ?SSHCommandResultInterface;

    public function getFirstSuccessfulResult(): ?SSHCommandResultInterface;

    public function getLastSuccessfulResult(): ?SSHCommandResultInterface;

    public function map(callable $function): SSHResultCollectionInterface;

    public function filter(callable $function): SSHResultCollectionInterface;

    public function first(?callable $function): ?SSHCommandResultInterface;

    public function last(?callable $function): ?SSHCommandResultInterface;

    public function count(?callable $function = null): int;

    public function wipe(): void;
}

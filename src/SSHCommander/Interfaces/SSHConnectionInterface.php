<?php

namespace Neskodi\SSHCommander\Interfaces;

use phpseclib\Net\SSH2;

interface SSHConnectionInterface
{
    public function setConfig(SSHConfigInterface $config);

    public function getConfig(?string $param = null);

    public function startTimer();

    public function stopTimer(): float;

    public function authenticate(): bool;

    public function setTimeout(int $timeout): SSHConnectionInterface;

    public function getSSH2(): SSH2;

    public function exec(SSHCommandInterface $command): SSHConnectionInterface;

    public function isAuthenticated(): bool;

    public function isValid(): bool;

    public function getStdOutLines(): array;

    public function getStdErrLines(): array;

    public function getLastExitCode(): ?int;

    public function resetOutput(): void;

    public function resetCommandConfig(): void;

    public function resetTimeout(): SSHConnectionInterface;
}

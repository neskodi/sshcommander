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

    public function execIsolated(SSHCommandInterface $command): SSHConnectionInterface;

    public function execInteractive(SSHCommandInterface $command): SSHConnectionInterface;

    public function read(): string;

    public function write(string $chars);

    public function writeAndSend(string $chars);

    public function isAuthenticated(): bool;

    public function isValid(): bool;

    public function getStdOutLines(): array;

    public function getStdErrLines(): array;

    public function getLastExitCode(): ?int;

    public function isTimeout(): bool;

    public function isTimelimit(): bool;

    public function isTimeoutOrTimelimit(): bool;

    public function resetOutput(): SSHConnectionInterface;

    public function resetCommandConfig(): SSHConnectionInterface;

    public function resetTimeout(): SSHConnectionInterface;

    public function resetQuietMode(): SSHConnectionInterface;

    public function setMarkerRegex(string $regex): SSHConnectionInterface;

    public function resetMarkers(): void;

    public function terminateCommand(): void;

    public function suspendCommand(): void;
}

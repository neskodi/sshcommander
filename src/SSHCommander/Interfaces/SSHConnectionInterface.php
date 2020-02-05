<?php

namespace Neskodi\SSHCommander\Interfaces;

use Neskodi\SSHCommander\Dependencies\SSH2;

interface SSHConnectionInterface
{
    public function setConfig(SSHConfigInterface $config);

    public function getConfig(?string $param = null);


    public function startTimer();

    public function stopTimer(): float;


    public function authenticate(): bool;

    public function isAuthenticated(): bool;

    public function authenticateIfNecessary(): void;

    public function isValid(): bool;


    public function getSSH2(): SSH2;


    public function setTimeout(int $timeout): SSHConnectionInterface;

    public function resetTimeout(): SSHConnectionInterface;

    public function enableQuietMode(): SSHConnectionInterface;

    public function resetQuietMode(): SSHConnectionInterface;

    public function resetSSH2Configuration(): SSHConnectionInterface;


    public function execIsolated(SSHCommandInterface $command): SSHConnectionInterface;

    public function execInteractive(SSHCommandInterface $command): SSHConnectionInterface;


    public function read(): string;

    public function write(string $chars);

    public function writeAndSend(string $chars);

    public function exec(SSHCommandInterface $command): void;

    public function getStdOutLines(): array;

    public function getStdErrLines(): array;

    public function getLastExitCode(): ?int;


    public function isTimeout(): bool;

    public function isTimelimit(): bool;

    public function isTimeoutOrTimelimit(): bool;


    public function resetResults(): SSHConnectionInterface;

    public function setMarkerRegex(string $regex): SSHConnectionInterface;

    public function resetMarkers(): SSHConnectionInterface;


    public function terminateCommand(): void;

    public function suspendCommand(): void;
}

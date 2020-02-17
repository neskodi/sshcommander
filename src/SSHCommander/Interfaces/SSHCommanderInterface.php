<?php

namespace Neskodi\SSHCommander\Interfaces;

/**
 * @method timeout(int $timeoutValue, string $condition, callable $behavior)
 * @method breakOnError(mixed $behavior)
 */
interface SSHCommanderInterface
{
    public static function setConfigFile(string $path);

    public function setConnection(
        SSHConnectionInterface $connection
    ): SSHCommanderInterface;

    public function getConnection(): SSHConnectionInterface;

    public function setInteractiveCommandRunner(
        SSHCommandRunnerInterface $commandRunner
    ): SSHCommanderInterface;

    public function getInteractiveCommandRunner(): SSHCommandRunnerInterface;

    public function createInteractiveCommandRunner(): SSHCommandRunnerInterface;

    public function setIsolatedCommandRunner(
        SSHCommandRunnerInterface $commandRunner
    ): SSHCommanderInterface;

    public function getIsolatedCommandRunner(): SSHCommandRunnerInterface;

    public function createIsolatedCommandRunner(): SSHCommandRunnerInterface;

    public function createCommand(
        $command,
        array $options = []
    ): SSHCommandInterface;

    public function run(
        $command,
        array $options = []
    ): SSHCommandResultInterface;

    public function runIsolated(
        $command,
        array $options = []
    ): SSHCommandResultInterface;

    public function getResultCollection(): ?SSHResultCollectionInterface;
}

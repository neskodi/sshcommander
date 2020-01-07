<?php

namespace Neskodi\SSHCommander\Interfaces;

interface SSHCommanderInterface
{
    public static function setConfigFile(string $path);

    public function setConnection(
        SSHConnectionInterface $connection
    ): SSHCommanderInterface;

    public function getConnection(): SSHConnectionInterface;

    public function setCommandRunner(
        SSHCommandRunnerInterface $commandRunner
    ): SSHCommanderInterface;

    public function getCommandRunner(): SSHCommandRunnerInterface;

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
}

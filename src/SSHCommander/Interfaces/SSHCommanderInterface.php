<?php

namespace Neskodi\SSHCommander\Interfaces;

use Psr\Log\LoggerInterface;

interface SSHCommanderInterface
{
    public function setConfig($config): SSHCommanderInterface;

    public function getConfig(?string $key = null);

    public function setLogger(LoggerInterface $logger);

    public function getLogger(): ?LoggerInterface;

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

    public static function setConfigFile(string $path);
}

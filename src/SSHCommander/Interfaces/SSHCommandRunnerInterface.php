<?php /** @noinspection PhpUnused */

namespace Neskodi\SSHCommander\Interfaces;

interface SSHCommandRunnerInterface
{
    public function exec(SSHCommandInterface $command): void;

    public function run(SSHCommandInterface $command);

    public function setConfig(SSHConfigInterface $config);

    public function getConfig(?string $param = null);

    public function mergeConfig($config, bool $missingOnly = false): SSHCommandRunnerInterface;

    public function setOption(string $key, $value): SSHCommandRunnerInterface;

    public function prepareCommand(SSHCommandInterface $command): SSHCommandInterface;
}

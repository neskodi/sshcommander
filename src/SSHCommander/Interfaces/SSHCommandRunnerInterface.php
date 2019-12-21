<?php /** @noinspection PhpUnused */

namespace Neskodi\SSHCommander\Interfaces;

interface SSHCommandRunnerInterface
{
    public function exec(SSHCommandInterface $command): void;

    public function run(SSHCommandInterface $command): SSHCommandResultInterface;

    public function setConfig(SSHConfigInterface $config);

    public function getConfig(?string $param = null);
}

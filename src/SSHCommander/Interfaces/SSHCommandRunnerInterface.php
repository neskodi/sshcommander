<?php /** @noinspection PhpUnused */

namespace Neskodi\SSHCommander\Interfaces;

interface SSHCommandRunnerInterface
{
    public function run(SSHCommandInterface $command): SSHCommandResultInterface;

    public function setConfig(SSHConfigInterface $config);

    public function getConfig(?string $param = null);
}

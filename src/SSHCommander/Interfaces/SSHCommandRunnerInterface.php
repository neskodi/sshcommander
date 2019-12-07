<?php /** @noinspection PhpUnused */

namespace Neskodi\SSHCommander\Interfaces;

interface SSHCommandRunnerInterface
{
    public function run(SSHCommandInterface $command): SSHCommandResultInterface;
}

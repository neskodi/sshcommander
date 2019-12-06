<?php /** @noinspection PhpUnused */

namespace Neskodi\SSHCommander\Interfaces;

use Psr\Log\LoggerInterface;

interface SSHCommandRunnerInterface
{
    public function run(SSHCommandInterface $command): SSHCommandResultInterface;

    public function getCommander(): SSHCommanderInterface;

    public function setLogger(LoggerInterface $logger);

    public function getLogger(): ?LoggerInterface;
}

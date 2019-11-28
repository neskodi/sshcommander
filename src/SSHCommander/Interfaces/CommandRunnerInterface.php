<?php

namespace Neskodi\SSHCommander\Interfaces;

use Psr\Log\LoggerInterface;

interface CommandRunnerInterface
{
    public function run(CommandInterface $command): CommandResultInterface;

    public function setLogger(LoggerInterface $logger): CommandResultInterface;

    public function getLogger(): ?LoggerInterface;
}

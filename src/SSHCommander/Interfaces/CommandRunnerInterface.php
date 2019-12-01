<?php

namespace Neskodi\SSHCommander\Interfaces;

use Neskodi\SSHCommander\SSHCommander;
use Psr\Log\LoggerInterface;

interface CommandRunnerInterface
{
    public function run(CommandInterface $command): CommandResultInterface;

    public function getCommander(): SSHCommander;

    public function setLogger(LoggerInterface $logger);

    public function getLogger(): ?LoggerInterface;
}

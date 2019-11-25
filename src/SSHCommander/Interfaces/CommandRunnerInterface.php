<?php

namespace Neskodi\SSHCommander\Interfaces;

use SSHCommander\Command;

interface CommandRunnerInterface
{
    public function run(CommandInterface $command): CommandResultInterface;
}

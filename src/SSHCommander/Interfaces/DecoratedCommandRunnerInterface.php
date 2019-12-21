<?php

namespace Neskodi\SSHCommander\Interfaces;

interface DecoratedCommandRunnerInterface
{
    public function __construct(DecoratedCommandRunnerInterface $runner);

    public function with(string $class): DecoratedCommandRunnerInterface;

    public function exec(SSHCommandInterface $command): void;
}

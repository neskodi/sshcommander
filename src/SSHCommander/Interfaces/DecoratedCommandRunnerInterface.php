<?php

namespace Neskodi\SSHCommander\Interfaces;

interface DecoratedCommandRunnerInterface
{
    public function __construct(DecoratedCommandRunnerInterface $runner);

    public function with(string $class): DecoratedCommandRunnerInterface;

    public function execDecorated(SSHCommandInterface $command): void;

    public function hasMethod(string $method): bool;
}

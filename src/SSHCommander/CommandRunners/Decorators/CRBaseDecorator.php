<?php

namespace Neskodi\SSHCommander\CommandRunners\Decorators;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;

abstract class CRBaseDecorator implements DecoratedCommandRunnerInterface
{
    /**
     * @var SSHCommandRunnerInterface
     */
    protected $runner;

    /**
     * Decorator constructor accepts either a pure command runner or a decorated
     * one (runner wrapped inside one or more other decorators).
     *
     * @param DecoratedCommandRunnerInterface $runner
     */
    public function __construct(DecoratedCommandRunnerInterface $runner)
    {
        $this->runner = $runner;
    }

    /**
     * Since decorator does not implement most of the CommandRunner methods,
     * we delegate them down the line until they are called on the initial
     * CommandRunner object. The most important method needed by almost all
     * decorators is getConnection().
     *
     * @param $name
     * @param $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->runner->$name(...$arguments);
    }

    public function hasMethod(string $method): bool
    {
        return $this->runner->hasMethod($method);
    }

    /**
     * Wrap this class with another decorator.
     *
     * @param string $class
     *
     * @return DecoratedCommandRunnerInterface
     */
    public function with(string $class): DecoratedCommandRunnerInterface
    {
        return new $class($this);
    }

    /**
     * Tell the original command runner to execute the command.
     * Each decorator will extend this method by adding
     * its own logic around the exec() call.
     *
     * @param SSHCommandInterface $command
     */
    abstract public function execDecorated(SSHCommandInterface $command): void;
}

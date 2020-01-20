<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\CommandRunners\Decorators\CRCleanupDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRTimeoutHandlerDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRErrorHandlerDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRConnectionDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRBasedirDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRLoggerDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRResultDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRTimerDecorator;
use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\ConfigAwareInterface;
use Neskodi\SSHCommander\Interfaces\LoggerAwareInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Traits\HasConnection;
use Neskodi\SSHCommander\Traits\ConfigAware;
use Neskodi\SSHCommander\Traits\HasResult;
use Neskodi\SSHCommander\Traits\Loggable;
use Psr\Log\LoggerInterface;

abstract class BaseCommandRunner implements
    LoggerAwareInterface,
    ConfigAwareInterface,
    SSHCommandRunnerInterface,
    DecoratedCommandRunnerInterface
{
    use Loggable, HasConnection, HasResult, ConfigAware;

    /**
     * BaseCommandRunner constructor.
     *
     * @param array|SSHConfigInterface $config
     * @param LoggerInterface|null     $logger
     */
    public function __construct(
        $config,
        ?LoggerInterface $logger = null
    ) {
        $this->setConfig($config);

        if ($logger instanceof LoggerInterface) {
            $this->setLogger($logger);
        }
    }

    /**
     * Wrap this command runner with a decorator.
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
     * Run the command.
     *
     * @param SSHCommandInterface $command the object containing the command to
     *                                     run
     *
     * @return SSHCommandResultInterface
     */
    public function run(SSHCommandInterface $command): SSHCommandResultInterface
    {
        // Add command decorators and execute the command.
        // !! ORDER MATTERS !!
        // (Some later decorators depend on earlier ones)
        $this->with(CRTimerDecorator::class)
             ->with(CRLoggerDecorator::class)
             ->with(CRResultDecorator::class)
             ->with(CRBasedirDecorator::class)
             ->with(CRErrorHandlerDecorator::class)
             ->with(CRTimeoutHandlerDecorator::class)
             ->with(CRCleanupDecorator::class)
             ->with(CRConnectionDecorator::class)

             ->execDecorated($command);

        return $this->getResult();
    }

    /**
     * Some decorators need to know whether a method is implemented by the
     * decorated runner, before calling it
     *
     * @param string $method
     *
     * @return bool
     */
    public function hasMethod(string $method): bool
    {
        return method_exists($this, $method);
    }

    /**
     * Save command exit code, stdout and stderr into the result object.
     *
     * @param SSHCommandInterface       $command
     * @param SSHCommandResultInterface $result
     */
    public function recordCommandResults(
        SSHCommandInterface $command,
        SSHCommandResultInterface $result
    ): void {
        $result->setOutput($this->getStdOutLines($command))
               ->setErrorOutput($this->getStdErrLines($command))
               ->setExitCode($this->getLastExitCode($command));
    }

    /**
     * Execute the command on the prepared connection.
     *
     * This method is called by decorators. If you need to bypass decorators,
     * for example, in case of  preliminary or post-commands, run
     * executeOnConnection directly.
     *
     * @param SSHCommandInterface $command
     */
    public function execDecorated(SSHCommandInterface $command): void
    {
        $this->executeOnConnection($command);
    }

    /**
     * Get the exit code of the last executed command.
     *
     * @param SSHCommandInterface $command
     *
     * @return int|null
     */
    abstract public function getLastExitCode(SSHCommandInterface $command): ?int;

    /**
     * Get the output lines of the last executed command.
     *
     * @param SSHCommandInterface $command
     *
     * @return array
     */
    abstract public function getStdOutLines(SSHCommandInterface $command): array;

    /**
     * Get the stderr lines of the last executed command.
     *
     * @param SSHCommandInterface $command
     *
     * @return array
     */
    abstract public function getStdErrLines(SSHCommandInterface $command): array;

    /**
     * Decorators and runner itself can use this method directly to bypass
     * (other) decorators.
     *
     * @param SSHCommandInterface $command
     */
    abstract public function executeOnConnection(SSHCommandInterface $command): void;
}

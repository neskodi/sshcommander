<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\CommandRunners\Decorators\CRConnectionDecorator;
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
use Neskodi\SSHCommander\SSHCommand;
use Neskodi\SSHCommander\SSHConfig;
use Psr\Log\LoggerInterface;

abstract class BaseCommandRunner implements
    LoggerAwareInterface,
    ConfigAwareInterface,
    SSHCommandRunnerInterface,
    DecoratedCommandRunnerInterface
{
    use Loggable, HasConnection, HasResult;
    use ConfigAware {
        set as protected configAwareSet;
    }

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
        $prepared = $this->prepareCommand($command);

        // Add command decorators and execute the command.
        // !! ORDER MATTERS !!
        $this->with(CRTimerDecorator::class)
             ->with(CRLoggerDecorator::class)
             ->with(CRResultDecorator::class)
             ->with(CRConnectionDecorator::class)
             ->exec($prepared);

        return $this->getResult();
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

    abstract public function exec(SSHCommandInterface $command): void;

    abstract public function getLastExitCode(SSHCommandInterface $command): ?int;

    abstract public function getStdOutLines(SSHCommandInterface $command): array;

    abstract public function getStdErrLines(SSHCommandInterface $command): array;

    /**
     * Prepend preliminary commands to the main command according to main
     * command configuration. If any preparation is necessary, such as moving
     * into basedir or setting the errexit option before running the main
     * command, another instance will be returned that contains the prepended
     * extra commands.
     *
     * @param SSHCommandInterface $command
     *
     * @return SSHCommandInterface
     */
    public function prepareCommand(SSHCommandInterface $command): SSHCommandInterface
    {
        $prepared = new SSHCommand($command);

        $this->prependBasedir($prepared);

        $this->prependErrexit($prepared);

        return $prepared;
    }

    /**
     * Prepend 'cd basedir' to the command so it starts running in the directory
     * specified by user.
     *
     * @param SSHCommandInterface $command
     */
    protected function prependBasedir(SSHCommandInterface $command): void
    {
        if ($basedir = $command->getConfig('basedir')) {
            $basedirCommand = sprintf('cd %s', $basedir);
            $command->prependCommand($basedirCommand);
        }

    }

    /**
     * Prepend 'set -e' to the command if user wants to always break on error.
     *
     * @param SSHCommandInterface $command
     */
    protected function prependErrexit(SSHCommandInterface $command): void
    {
        if (SSHConfig::BREAK_ON_ERROR_ALWAYS === $command->getConfig('break_on_error')) {
            // turn on errexit mode
            $command->prependCommand('set -e');
        } else {
            // turn off this mode because it may possibly be enabled by previous
            // commands
            $command->prependCommand('set +e');
        }
    }

    /**
     * This delegation is here because SSHCommandInterface declares a specific
     * return type which the trait is not aware about.
     *
     * @param      $param
     * @param null $value
     *
     * @return SSHCommandRunnerInterface
     * @noinspection PhpHierarchyChecksInspection - PHPStorm bug
     * @noinspection PhpIncompatibleReturnTypeInspection
     */
    public function set($param, $value = null): SSHCommandRunnerInterface
    {
        return $this->configAwareSet($param, $value);
    }
}

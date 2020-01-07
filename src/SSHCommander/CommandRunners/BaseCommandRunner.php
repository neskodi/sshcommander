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
use Neskodi\SSHCommander\SSHCommand;
use Neskodi\SSHCommander\Traits\ConfigAware;
use Neskodi\SSHCommander\Traits\Loggable;
use Psr\Log\LoggerInterface;

abstract class BaseCommandRunner implements
    LoggerAwareInterface,
    ConfigAwareInterface,
    SSHCommandRunnerInterface,
    DecoratedCommandRunnerInterface
{
    use Loggable, ConfigAware;

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

    abstract public function exec(SSHCommandInterface $command): void;

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
        if (!$command->getConfig('basedir')) {
            // no need to prepare
            return $command;
        }

        $prepared = new SSHCommand($command);

        $this->prependBasedir($prepared);

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
        $basedirCommand = sprintf('cd %s', $command->getConfig('basedir'));
        $command->prependCommand($basedirCommand);
    }

    /**
     * Command runner should be allowed to merge chunks of config without validation.
     *
     * @return bool
     */
    protected function skipConfigValidation(): bool
    {
        return true;
    }
}

<?php

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\CommandRunners\InteractiveCommandRunner;
use Neskodi\SSHCommander\CommandRunners\IsolatedCommandRunner;
use Neskodi\SSHCommander\Exceptions\CommandRunException;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandRunnerInterface;
use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommanderInterface;
use Neskodi\SSHCommander\Interfaces\ConfigAwareInterface;
use Neskodi\SSHCommander\Interfaces\LoggerAwareInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Factories\LoggerFactory;
use Neskodi\SSHCommander\Traits\ConfigAware;
use Neskodi\SSHCommander\Traits\Loggable;
use Psr\Log\LoggerInterface;
use Exception;

class SSHCommander implements
    SSHCommanderInterface,
    LoggerAwareInterface,
    ConfigAwareInterface
{
    use Loggable, ConfigAware;

    /**
     * @var SSHConnectionInterface
     */
    protected $connection;

    /**
     * @var SSHCommandRunnerInterface
     */
    protected $commandRunner;

    /**
     * @var SSHCommandRunnerInterface
     */
    protected $isolatedCommandRunner;

    /**
     * SSHCommander constructor.
     *
     * @param array|SSHConfig      $config
     *
     * @param LoggerInterface|null $logger
     *
     * @throws Exception
     */
    public function __construct($config, ?LoggerInterface $logger = null)
    {
        $this->setConfig($config);

        if (
            ($logger instanceof LoggerInterface) ||
            ($logger = LoggerFactory::makeLogger($this->config))
        ) {
            $this->setLogger($logger);
        }
    }

    /**
     * Fluent setter for the SSHConnection object, in case you need to override
     * the default one.
     *
     * @param SSHConnectionInterface $connection
     *
     * @return SSHCommanderInterface
     */
    public function setConnection(
        SSHConnectionInterface $connection
    ): SSHCommanderInterface {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Get the SSHConnection object.
     *
     * @return SSHConnectionInterface
     *
     * @throws Exceptions\AuthenticationException
     */
    public function getConnection(): SSHConnectionInterface
    {
        if (!$this->connection) {
            $this->setConnection(
                new SSHConnection(
                    $this->getConfig(),
                    $this->getLogger()
                )
            );
        }

        return $this->connection;
    }

    /**
     * Fluent setter for the command runner object, in case you need to override
     * the default one.
     *
     * @param SSHCommandRunnerInterface $commandRunner
     *
     * @return SSHCommanderInterface
     */
    public function setCommandRunner(SSHCommandRunnerInterface $commandRunner
    ): SSHCommanderInterface {
        $this->commandRunner = $commandRunner;

        return $this;
    }

    /**
     * Get the command runner object.
     *
     * @return SSHCommandRunnerInterface
     *
     * @throws Exceptions\AuthenticationException
     */
    public function getCommandRunner(): SSHCommandRunnerInterface
    {
        if (!$this->commandRunner) {
            $commandRunner = $this->createCommandRunner();

            $this->setCommandRunner($commandRunner);
        }

        return $this->commandRunner;
    }

    /**
     * Get the isolated command runner object.
     *
     * @return SSHCommandRunnerInterface
     *
     * @throws Exceptions\AuthenticationException
     */
    public function getIsolatedCommandRunner(): SSHCommandRunnerInterface
    {
        return $this->isolatedCommandRunner;
    }

    /**
     * Create a command object from the provided string or array of commands.
     * If a Command object was already provided, short circuit.
     *
     * @param string|array|SSHCommandInterface $command
     * @param array                            $options optional command
     *                                                  parameters, such as
     *                                                  break_on_error
     *
     * @return SSHCommandInterface
     *
     * @throws AuthenticationException
     */
    public function createCommand(
        $command,
        array $options = []
    ): SSHCommandInterface {
        // Get the final set of options that apply to this command run.
        $options = $this->getMergedOptions($command, $options);

        if ($command instanceof SSHCommandInterface) {
            $command->mergeConfig($options);
        } else {
            $command = new SSHCommand($command, $options);
        }

        return $command;
    }

    /**
     * Run a command or a series of commands.
     *
     * @param string|array|SSHCommandInterface $command the command to run
     * @param array                            $options optional parameters
     *
     * @return SSHCommandResultInterface
     *
     * @throws Exceptions\AuthenticationException
     * @throws CommandRunException
     */
    public function run(
        $command,
        array $options = []
    ): SSHCommandResultInterface {
        $commandRunner = $this->getCommandRunner();

        return $this->runWith($commandRunner, $command, $options);
    }

    /**
     * Run a command or a series of commands in an isolated environment.
     *
     * @param string|array|SSHCommandInterface $command the command to run
     * @param array                            $options optional parameters
     *
     * @return SSHCommandResultInterface
     *
     * @throws Exceptions\AuthenticationException
     * @throws CommandRunException
     */
    public function runIsolated(
        $command,
        array $options = []
    ): SSHCommandResultInterface {
        $this->isolatedCommandRunner = $this->createCommandRunner(
            IsolatedCommandRunner::class
        );

        return $this->runWith($this->isolatedCommandRunner, $command, $options);
    }

    /**
     * Check the result for error code, and if we are currently configured to
     * break on error, throw the exception.
     *
     * @param SSHCommandInterface       $command
     * @param SSHCommandResultInterface $result
     *
     * @throws CommandRunException
     */
    protected function checkForError(
        SSHCommandInterface $command,
        SSHCommandResultInterface $result
    ): void {
        if ($result->isError() && $command->getConfig('break_on_error')) {
            throw new CommandRunException;
        }
    }

    /**
     * Run the command with the prepared runner.
     *
     * @param SSHCommandRunnerInterface $runner
     * @param SSHCommandInterface       $command
     * @param array                     $options
     *
     * @return SSHCommandResultInterface
     * @throws AuthenticationException
     * @throws CommandRunException
     */
    protected function runWith(
        SSHCommandRunnerInterface $runner,
        $command,
        array $options = []
    ): SSHCommandResultInterface {
        $commandObject = $this->createCommand($command, $options);

        $result = $runner->run($commandObject);

        $this->checkForError($commandObject, $result);

        return $result;
    }

    /**
     * Set the config file location. Can be called before instantiating
     * any class.
     *
     * @param string $path location of config.php
     */
    public static function setConfigFile(string $path)
    {
        SSHConfig::setConfigFileLocation($path);
    }

    /**
     * Create a command runner object that will be an instance of the given
     * command runner class.
     *
     * @param string $class
     *
     * @return mixed
     *
     * @throws AuthenticationException
     */
    protected function createCommandRunner(
        string $class = InteractiveCommandRunner::class
    ): SSHCommandRunnerInterface {
        $commandRunner = new $class(
            $this->getConfig(),
            $this->getLogger()
        );

        $commandRunner->setConnection($this->getConnection());

        return $commandRunner;
    }

    /**
     * Override most general set of options with more specific sets.
     *
     * First, the global options of this SSHCommander object are applied,
     * then the sequence-level options (if any), then the options from the
     * source command, then the options passed to the current running command.
     *
     * @param string|array|SSHCommandInterface $command
     * @param array                            $options
     *
     * @return array
     * @throws AuthenticationException
     */
    protected function getMergedOptions(
        $command,
        array $options
    ): array {
        return array_merge(
        // global config options of this SSHCommander instance
            $this->getConfig()->all(),

            // if passed command is an object having its own options, they will
            // apply here
            ($command instanceof SSHCommandInterface) ? $command->getConfig()->all() : [],

            // finally, merge command level options provided immediately at command
            // run time
            $options
        );
    }
}

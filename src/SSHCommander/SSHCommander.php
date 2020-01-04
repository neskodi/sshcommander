<?php

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Interfaces\SSHSequenceCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHResultCollectionInterface;
use Neskodi\SSHCommander\CommandRunners\SequenceCommandRunner;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandRunnerInterface;
use Neskodi\SSHCommander\CommandRunners\RemoteCommandRunner;
use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommanderInterface;
use Neskodi\SSHCommander\Interfaces\ConfigAwareInterface;
use Neskodi\SSHCommander\Interfaces\LoggerAwareInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Exceptions\CommandRunException;
use Neskodi\SSHCommander\Factories\LoggerFactory;
use Neskodi\SSHCommander\Traits\ConfigAware;
use Neskodi\SSHCommander\Traits\Loggable;
use Psr\Log\LoggerInterface;
use Exception;
use Closure;

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

        if ($logger instanceof LoggerInterface) {
            $this->setLogger($logger);
        } elseif ($logger = LoggerFactory::makeLogger($this->config)) {
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
     */
    public function run(
        $command,
        array $options = []
    ): SSHCommandResultInterface {
        $commandRunner = $this->getCommandRunner();

        $commandObject = $this->createCommand($command, $options);

        return $commandRunner->run($commandObject);
    }

    /**
     * Run user's function that may call subsequent SSH commands in the context
     * of a persistent SSH session.
     *
     * Unlike the simple RemoteCommandRunner that relies on the 'exec' method
     * from phpseclib, this one will use 'write' and 'read' methods, thus
     * working with a PTY and preserving session state
     * such as working directory, current user change with sudo, variable
     * assignments etc.
     *
     * @noinspection PhpRedundantCatchClauseInspection
     *
     * @param Closure $actions callable function that contains logic to run
     * @param array   $options options that will be common for all commands
     *                         during this session, unless overridden by a
     *                         particular command.
     *
     * @return SSHResultCollectionInterface
     *
     * @throws AuthenticationException
     */
    public function sequence(
        Closure $actions,
        array $options = []
    ): SSHResultCollectionInterface {
        $this->startSequence($options);

        try {
            call_user_func($actions, $this, $options);
        } catch (CommandRunException $exception) {
            $this->processSequenceError($exception, $options);
        }

        $result = $this->getCommandRunner()->getResultCollection();

        $this->endSequence();

        return $result;
    }

    /**
     * Start the ssh session by creating an instance of SequenceCommandRunner.
     *
     * @param array $options
     *
     * @throws AuthenticationException
     */
    protected function startSequence(array $options = []): void
    {
        $this->setCommandRunner(
            $this->createCommandRunner(
                SequenceCommandRunner::class
            )
        );

        /** @var SSHSequenceCommandRunnerInterface $runner */
        $runner = $this->getCommandRunner();

        $runner->mergeConfig($options)->initSequence();
    }

    /**
     * End the sequence by destroying the SequenceCommandRunner.
     *
     * @return void
     */
    protected function endSequence(): void
    {
        $this->commandRunner = null;
    }

    /**
     * Log any errors that happened in the middle of sequence and weren't
     * discarded by 'break_on_error' => false, then throw an Exception
     * to user's app.
     *
     * @param CommandRunException $exception
     * @param array               $options
     */
    protected function processSequenceError(
        CommandRunException $exception,
        array $options
    ): void {
        // TODO: implement
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
        string $class = RemoteCommandRunner::class
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
        $commandRunner = $this->getCommandRunner();

        $merged = array_merge(
            // global config options of this SSHCommander instance
            $this->getConfig()->all(),

            // override with sequence-level options
            $commandRunner->getConfig()->all(),

            // if passed command is an object having its own options, they will
            // apply here
            ($command instanceof SSHCommandInterface) ? $command->getConfig()->all() : []
        );

        // Up to runner to clean any options that should not propagate
        // to commands from above
        if (
            is_object($commandRunner) &&
            method_exists($commandRunner, 'filterCommandOptionsBeforeRun')
        ) {
            $commandRunner->filterCommandOptionsBeforeRun($options);
        }

        // finally, merge command level options provided immediately at command
        // run time
        $merged = array_merge($merged, $options);

        return $merged;
    }
}

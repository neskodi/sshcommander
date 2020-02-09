<?php

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\CommandRunners\InteractiveCommandRunner;
use Neskodi\SSHCommander\CommandRunners\IsolatedCommandRunner;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandRunnerInterface;
use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommanderInterface;
use Neskodi\SSHCommander\Interfaces\ConfigAwareInterface;
use Neskodi\SSHCommander\Interfaces\LoggerAwareInterface;
use Neskodi\SSHCommander\Exceptions\CommandRunException;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Factories\LoggerFactory;
use Neskodi\SSHCommander\Traits\HasResultCollection;
use Neskodi\SSHCommander\Traits\SetsConfigValues;
use Neskodi\SSHCommander\Traits\ConfigAware;
use Neskodi\SSHCommander\Traits\Loggable;
use Psr\Log\LoggerInterface;
use Exception;

class SSHCommander implements
    SSHCommanderInterface,
    LoggerAwareInterface,
    ConfigAwareInterface
{
    use Loggable, ConfigAware, SetsConfigValues;
    use HasResultCollection;

    /**
     * @var SSHConnectionInterface
     */
    protected $connection;

    /**
     * @var SSHCommandRunnerInterface
     */
    protected $interactiveCommandRunner;

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

        // if user wants to autologin, initiate connection at once
        if ($this->getConfig('autologin')) {
            $this->createConnection();
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
            $this->createConnection();
        }

        return $this->connection;
    }

    /**
     * Create a new SSHConnection object and save it for usage in this instance
     * of SSHCommander.
     *
     * @throws AuthenticationException
     */
    protected function createConnection()
    {
        $this->setConnection(new SSHConnection(
            $this->getConfig(),
            $this->getLogger()
        ));
    }

    /**
     * Fluent setter for the interactive command runner object, in case you need
     * to override the default one before running a command.
     *
     * @param SSHCommandRunnerInterface $commandRunner
     *
     * @return $this
     */
    public function setInteractiveCommandRunner(
        SSHCommandRunnerInterface $commandRunner
    ): SSHCommanderInterface {
        $this->interactiveCommandRunner = $commandRunner;

        return $this;
    }

    /**
     * Get the interactive command runner object. If one does not exist, make a
     * new instance.
     *
     * @return SSHCommandRunnerInterface
     * @throws AuthenticationException
     */
    public function getInteractiveCommandRunner(): SSHCommandRunnerInterface
    {
        if (!$this->interactiveCommandRunner) {
            $this->setInteractiveCommandRunner(
                $this->createInteractiveCommandRunner()
            );
        }

        return $this->interactiveCommandRunner;
    }

    /**
     * Create an instance of an interactive command runner.
     *
     * @return mixed|SSHCommandRunnerInterface
     * @throws AuthenticationException
     */
    public function createInteractiveCommandRunner(): SSHCommandRunnerInterface
    {
        return $this->createCommandRunner(InteractiveCommandRunner::class);
    }

    /**
     * Fluent setter for the isolated command runner object, in case you need
     * to override the default one before running a command.
     *
     * @param SSHCommandRunnerInterface $commandRunner
     *
     * @return $this
     */
    public function setIsolatedCommandRunner(
        SSHCommandRunnerInterface $commandRunner
    ): SSHCommanderInterface {
        $this->isolatedCommandRunner = $commandRunner;

        return $this;
    }

    /**
     * Get the isolated command runner object.
     *
     * @return SSHCommandRunnerInterface
     * @throws AuthenticationException
     */
    public function getIsolatedCommandRunner(): SSHCommandRunnerInterface
    {
        if (!$this->isolatedCommandRunner) {
            $this->setIsolatedCommandRunner(
                $this->createIsolatedCommandRunner()
            );
        }

        return $this->isolatedCommandRunner;
    }

    /**
     * Create an instance of an isolated command runner.
     *
     * @return mixed|SSHCommandRunnerInterface
     * @throws AuthenticationException
     */
    public function createIsolatedCommandRunner(): SSHCommandRunnerInterface
    {
        return $this->createCommandRunner(IsolatedCommandRunner::class);
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
            $command->set($options);
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
        return $this->runWith(
            $this->getInteractiveCommandRunner(),
            $command,
            $options
        );
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
        return $this->runWith(
            $this->getIsolatedCommandRunner(),
            $command,
            $options
        );
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

        // put result into the collection
        $this->getResultCollection()[] = $result;

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
        SSHConfig::setUserConfigFileLocation($path);
    }

    /**
     * Create a command runner object that will be an instance of the given
     * command runner class. Inject the connection object that we have by this
     * time.
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
     */
    protected function getMergedOptions(
        $command,
        array $options
    ): array {
        return array_merge(
        // global configuration of this SSHCommander instance
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

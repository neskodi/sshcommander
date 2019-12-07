<?php

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandRunnerInterface;
use Neskodi\SSHCommander\CommandRunners\RemoteCommandRunner;
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
            $commandRunner = new RemoteCommandRunner(
                $this->getConfig(),
                $this->getLogger()
            );

            $commandRunner->setConnection($this->getConnection());

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
     */
    public function createCommand(
        $command,
        array $options = []
    ): SSHCommandInterface {
        if ($command instanceof SSHCommandInterface) {
            $options = $command->getConfig()->all();
            $this->addDefaultCommandOptions($options);
            $command->setOptions($options);
        } else {
            $this->addDefaultCommandOptions($options);
            $command = new SSHCommand($command, $options);
        }

        return $command;
    }

    /**
     * Pass some global options to command by default, unless they were
     * specified.
     *
     * @param array $options
     */
    protected function addDefaultCommandOptions(array &$options)
    {
        $config = $this->getConfig()->all();

        $options = array_merge($config, $options);
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
     * Set the config file location. Can be called before instantiating
     * any class.
     *
     * @param string $path location of config.php
     */
    public static function setConfigFile(string $path)
    {
        SSHConfig::setConfigFileLocation($path);
    }
}

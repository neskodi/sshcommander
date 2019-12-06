<?php

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandRunnerInterface;
use Neskodi\SSHCommander\CommandRunners\RemoteCommandRunner;
use Neskodi\SSHCommander\CommandRunners\LocalCommandRunner;
use Neskodi\SSHCommander\Exceptions\InvalidConfigException;
use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommanderInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Factories\LoggerFactory;
use Neskodi\SSHCommander\Traits\Loggable;
use Exception;

class SSHCommander implements SSHCommanderInterface
{
    use Loggable;

    /**
     * @var SSHConfigInterface
     */
    protected $config;

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
     * @param array|SSHConfig $config
     *
     * @throws Exception
     */
    public function __construct($config)
    {
        $this->setConfig($config);

        if ($logger = LoggerFactory::makeLogger($this->config)) {
            $this->setLogger($logger);
        }
    }

    /**
     * Fluent setter for the SSHConfig object, in case you need to override the
     * default one.
     *
     * @param array|SSHConfigInterface $config
     *
     * @return SSHCommanderInterface
     */
    public function setConfig($config): SSHCommanderInterface
    {
        if (is_array($config)) {
            $configObject = new SSHConfig($config);
        } elseif ($config instanceof SSHConfig) {
            $configObject = $config;
        } else {
            throw new InvalidConfigException(gettype($config));
        }

        $this->config = $configObject;

        return $this;
    }

    /**
     * Get the configuration object, or a specific value from the configuration
     * object.
     *
     * @param string|null $key
     *
     * @return SSHConfig|mixed
     */
    public function getConfig(?string $key = null)
    {
        return $key ? $this->config->get($key) : $this->config;
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
                new SSHConnection($this->getConfig(), $this->getLogger())
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
     */
    public function getCommandRunner(): SSHCommandRunnerInterface
    {
        if (!$this->commandRunner) {
            $commandRunner = $this->getConfig()->isLocal()
                ? new LocalCommandRunner($this)
                : new RemoteCommandRunner($this);

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
            return $command;
        }

        $this->setDefaultCommandOptions($options);

        return new SSHCommand($command, $options);
    }

    /**
     * Pass some global options to command by default, unless they were
     * specified.
     *
     * @param array $options
     */
    protected function setDefaultCommandOptions(array &$options)
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

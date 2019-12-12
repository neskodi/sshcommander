<?php /** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpUndefinedVariableInspection */

/** @noinspection PhpUnusedLocalVariableInspection */

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Exceptions\InvalidCommandException;
use Neskodi\SSHCommander\Interfaces\ConfigAwareInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Traits\ConfigAware;

class SSHCommand implements SSHCommandInterface, ConfigAwareInterface
{
    use ConfigAware;

    /**
     * One command object may hold multiple commands to execute in one run.
     * Hence an array.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Command constructor.
     *
     * @param string|array             $command - single command as string
     *                                          - multiple commands as strings separated by newlone
     *                                          - multiple commands as array
     * @param array|SSHConfigInterface $config
     */
    public function __construct($command, $config)
    {
        $this->setConfig($config)
             ->setCommand($command);
    }

    /**
     * Fluent setter for the command to execute.
     *
     * @param array|string|SSHCommandInterface $command
     *   - single command as string
     *   - multiple commands as strings separated by delimiter_split_input
     *   - multiple commands as array
     *
     * @return SSHCommandInterface
     */
    public function setCommand($command): SSHCommandInterface
    {
        $this->commands = $this->sanitizeInput($command);

        return $this;
    }

    /**
     * Get the command as string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getCommands(true, true);
    }

    /**
     * Return the commands as a string in a format most appropriate to write to
     * the log.
     *
     * @param string $delimiter the delimiter to use to separate commands.
     *
     * @return string
     */
    public function toLoggableString($delimiter = ';'): string
    {
        $preparedCommands = $this->getPreparedCommands();

        return implode($delimiter, $preparedCommands);
    }

    /**
     * Get all commands as a string or an array.
     *
     * @param bool $asString pass false to get commands as an array.
     *
     * @param bool $prepared perform preparation before actual run, for example
     *                       prepend 'cd basedir' and 'sed -e' for breaking on
     *                       errors.
     *
     * @return array|string
     */
    public function getCommands(bool $asString = true, bool $prepared = true)
    {
        $commands = $prepared
            ? $this->getPreparedCommands()
            : $this->commands;

        return $asString
            ? implode($this->getConfig('delimiter_join_input'), $commands)
            : $commands;
    }

    /**
     * Set a specific option.
     *
     * @param string $key   the name of the option to set.
     * @param mixed  $value the value of the option.
     *
     * @return SSHCommandInterface
     */
    public function setOption(string $key, $value): SSHCommandInterface
    {
        $this->config->set($key, $value);

        return $this;
    }

    /**
     * Set a specific option.
     *
     * @param array $options
     *
     * @return SSHCommandInterface
     */
    public function setOptions(array $options = []): SSHCommandInterface
    {
        foreach ($options as $key => $value) {
            $this->config->set($key, $value);
        }

        return $this;
    }

    /**
     * Appends a command or commands to the end of the execution sequence.
     *
     * @param string|array $command
     *
     * @return $this
     */
    public function appendCommand($command): SSHCommandInterface
    {
        $commands = $this->sanitizeInput($command);

        $this->commands = array_merge($this->commands, $commands);

        return $this;
    }

    /**
     * Prepends a command or commands to the beginning of the execution sequence.
     *
     * @param array|string $command
     *
     * @return $this
     */
    public function prependCommand($command): SSHCommandInterface
    {
        $commands = $this->sanitizeInput($command);

        $this->commands = array_merge($commands, $this->commands);

        return $this;
    }

    /**
     * Inject user input, be it a string, an array or another command object.
     * Throw an exception if input is invalid.
     *
     * @param $command
     *
     * @return array
     */
    protected function sanitizeInput($command): array
    {
        if (
            !is_array($command) &&
            !is_string($command) &&
            !$command instanceof SSHCommandInterface
        ) {
            throw new InvalidCommandException(gettype($command));
        }

        if ($command instanceof SSHCommandInterface) {
            $arrayCommands = $command->getCommands(false, false);
        }

        if (is_array($command)) {
            $arrayCommands = $command;
        }

        if (is_string($command)) {
            $delimiter = $this->getConfig('delimiter_split_input');

            $arrayCommands = explode($delimiter, $command);
        }

        $arrayCommands = array_map('trim', $arrayCommands);

        return $arrayCommands;
    }

    /**
     * Perform preparation before actual run, for example prepend 'cd basedir'
     * and 'sed -e' for breaking on errors.
     *
     * @return array
     */
    protected function getPreparedCommands(): array
    {
        $commands = $this->commands;

        if ($basedir = $this->getConfig('basedir')) {
            array_unshift($commands, 'cd ' . $basedir);
        }

        if ($this->getConfig('break_on_error')) {
            array_unshift($commands, 'set -e');
        }

        return $commands;
    }

    /**
     * Config passed to command is not required to contain valid
     * or any at all connection information, so ask SSHConfig to skip
     * validation.
     *
     * @return bool
     */
    protected function skipValidation(): bool
    {
        return true;
    }
}

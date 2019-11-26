<?php

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Exceptions\InvalidCommandException;
use Neskodi\SSHCommander\Interfaces\CommandInterface;

class Command implements CommandInterface
{
    /**
     * One command object may hold multiple commands to execute in one run.
     * Hence an array.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * You may pass any additional options necessary to build the command.
     *
     * @var array
     */
    protected $options = [];

    /**
     * Command constructor.
     *
     * @param string|array $command - single command as string
     *                              - multiple commands as strings separated by newlone
     *                              - multiple commands as array
     * @param array        $options any additional options
     */
    public function __construct($command, array $options = [])
    {
        $this->setOptions($options)
             ->setCommand($command);
    }

    /**
     * Fluent setter for the command to execute.
     *
     * @param array|string $command - single command as string
     *                              - multiple commands as strings separated by
     *                              newline
     *                              - multiple commands as array
     *
     * @return CommandInterface
     */
    public function setCommand($command): CommandInterface
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
            ? implode($this->getOption('delimiter_join_input'), $commands)
            : $commands;
    }

    /**
     * Set all command options as an array in one function.
     * Deletes and rewrites all options set previously.
     *
     * @param array $options command options
     *
     * @return CommandInterface
     */
    public function setOptions(array $options = []): CommandInterface
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Set a specific option.
     *
     * @param string $key   the name of the option to set.
     * @param mixed  $value the value of the option.
     *
     * @return CommandInterface
     */
    public function setOption(string $key, $value): CommandInterface
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Get all additional options that were passed to the command.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get a specific option from the array of additional options.
     *
     * @param string $key option name.
     *
     * @return mixed|null
     */
    public function getOption(string $key)
    {
        return array_key_exists($key, $this->options)
            ? $this->options[$key]
            : null;
    }

    /**
     * Appends a command or commands to the end of the execution sequence.
     *
     * @param string|array $command
     *
     * @return $this
     */
    public function appendCommand($command): CommandInterface
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
    public function prependCommand($command): CommandInterface
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
        if ($command instanceof CommandInterface) {
            return $command->getCommands(false);
        }

        if (is_array($command)) {
            return $command;
        }

        if (is_string($command)) {
            $delimiter = $this->getOption('delimiter_split_input');

            return explode($delimiter, $command);
        }

        throw new InvalidCommandException(gettype($command));
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

        if ($basedir = $this->getOption('basedir')) {
            array_unshift($commands, 'cd ' . $basedir);
        }

        if ($this->getOption('break_on_error')) {
            array_unshift($commands, 'set -e');
        }

        return $commands;
    }
}

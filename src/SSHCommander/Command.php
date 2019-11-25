<?php

namespace Neskodi\SSHCommander;

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
        if (is_string($command)) {
            $delimiter      = $this->getOption('delimiter_split_input');
            $this->commands = explode($delimiter, $command);
        } elseif (is_array($command)) {
            $this->commands = $command;
        }

        return $this;
    }

    /**
     * Get the command as string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getCommands();
    }

    /**
     * Get all commands as a string or an array.
     *
     * @param bool $asString pass false to get commands as an array.
     *
     * @return array|string
     */
    public function getCommands(bool $asString = true)
    {
        $commands = $this->commands;
        if ($this->breaksOnError()) {
            array_unshift($commands, 'set -e');
        }

        return $asString
            ? implode($this->getOption('delimiter_join_input'), $this->commands)
            : $this->commands;
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
     * Whether this command should stop execution on error. (Default: false)
     *
     * If multiple commands are passed, they will be executed sequentially.
     * Pass ['break_on_error' => true] among command options to stop execution
     * on first error.
     *
     * @return bool
     */
    public function breaksOnError(): bool
    {
        return array_key_exists('break_on_error', $this->options) &&
               $this->options['break_on_error'];
    }
}

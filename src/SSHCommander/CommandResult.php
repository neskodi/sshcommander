<?php

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Interfaces\CommandInterface;
use Neskodi\SSHCommander\Interfaces\CommandResultInterface;

class CommandResult implements CommandResultInterface
{
    const STATUS_OK = 'ok';
    const STATUS_ERROR = 'error';

    /**
     * @var CommandInterface
     */
    protected $command;

    /**
     * @var string
     */
    protected $delimiter = PHP_EOL;

    /**
     * @var int
     */
    protected $exitCode;

    /**
     * @var array
     */
    protected $outputLines = [];

    /**
     * @var array
     */
    protected $errorLines = [];

    /**
     * CommandResult constructor.
     *
     * @param CommandInterface $command the command that was run
     * @param array            $result ['exitcode', 'out', ?'err']
     */
    public function __construct(CommandInterface $command, array $result)
    {
        $this->setCommand($command)
             ->setExitCode($result['exitcode'])
             ->setOutput((array)$result['out']);

        if (array_key_exists('err', $result)) {
            $this->setErrorOutput((array)$result['err']);
        }
    }

    /**
     * Fluent setter for the command object.
     *
     * @param CommandInterface $command
     *
     * @return CommandResultInterface
     */
    public function setCommand(CommandInterface $command): CommandResultInterface
    {
        $this->command = $command;

        return $this;
    }

    /**
     * Get the command object.
     *
     * @return CommandInterface
     */
    public function getCommand(): CommandInterface
    {
        return $this->command;
    }

    /**
     * Fluent setter for the exit code of the command.
     *
     * @param int $code
     *
     * @return CommandResultInterface
     */
    public function setExitCode(int $code): CommandResultInterface
    {
        $this->exitCode = $code;

        return $this;
    }

    /**
     * Fluent setter for the output lines of the command.
     *
     * @param array $output an array of output lines.
     *
     * @return CommandResultInterface
     */
    public function setOutput(array $output): CommandResultInterface
    {
        $this->outputLines = $output;

        return $this;
    }

    /**
     * Fluent setter for the stderr lines of the command.
     *
     * @param array $output an array of output lines.
     *
     * @return CommandResultInterface
     */
    public function setErrorOutput(array $output): CommandResultInterface
    {
        $this->errorLines = $output;

        return $this;
    }

    /**
     * Get the status of the command as 'ok' or 'error'.
     *
     * @return string
     */
    public function getStatus(): string
    {
        if (0 === $this->exitCode) {
            return static::STATUS_OK;
        }

        return static::STATUS_ERROR;
    }

    /**
     * Get the exit code of the command as integer.
     *
     * @return int
     */
    public function getExitCode(): int
    {
        return (int)$this->exitCode;
    }

    /**
     * Get output lines as a string or as an array.
     *
     * @param bool $asString
     *
     * @return array|string
     */
    public function getOutput(bool $asString = false)
    {
        return $asString
            ? implode($this->delimiter, $this->outputLines)
            : $this->outputLines;
    }

    /**
     * Get error output lines as a string or as an array.
     *
     * @param bool $asString
     *
     * @return array|string
     */
    public function getStdErrOutput(bool $asString = false)
    {
        return $asString
            ? implode($this->delimiter, $this->errorLines)
            : $this->errorLines;
    }

    /**
     * Get command output as string - for cases where this object is
     * explicitly used in a string context.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getOutput(true);
    }

    /**
     * Convenience method to tell if the command returned the success exit code
     * (0).
     *
     * @return bool
     */
    public function isOk(): bool
    {
        return static::STATUS_OK === $this->getStatus();
    }

    /**
     * Convenience method to tell if the command returned an error exit code.
     *
     * @return bool
     */
    public function isError(): bool
    {
        return static::STATUS_ERROR === $this->getStatus();
    }

    /**
     * Fluent setter for the output delimiter character or string that will be
     * used when returning command output lines as string.
     *
     * @param string $delim
     *
     * @return CommandResultInterface
     */
    public function setOutputDelimiter(string $delim): CommandResultInterface
    {
        $this->delimiter = $delim;

        return $this;
    }
}

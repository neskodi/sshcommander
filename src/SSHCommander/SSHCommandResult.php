<?php

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Traits\Loggable;

class SSHCommandResult implements SSHCommandResultInterface
{
    use Loggable;

    const STATUS_OK = 'ok';
    const STATUS_ERROR = 'error';

    /**
     * @var SSHCommandInterface
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
     * @param SSHCommandInterface $command the command that was run
     * @param array               $result  ['exitcode', 'out', ?'err']
     */
    public function __construct(SSHCommandInterface $command)
    {
        $this->setCommand($command);
    }

    /**
     * Fluent setter for the command object.
     *
     * @param SSHCommandInterface $command
     *
     * @return SSHCommandResultInterface
     */
    public function setCommand(SSHCommandInterface $command
    ): SSHCommandResultInterface {
        $this->command = $command;

        return $this;
    }

    /**
     * Get the command object.
     *
     * @return SSHCommandInterface
     */
    public function getCommand(): SSHCommandInterface
    {
        return $this->command;
    }

    /**
     * Fluent setter for the exit code of the command.
     *
     * @param int $code
     *
     * @return SSHCommandResultInterface
     */
    public function setExitCode(int $code): SSHCommandResultInterface
    {
        $this->exitCode = $code;

        return $this;
    }

    /**
     * Fluent setter for the output lines of the command.
     *
     * @param array $lines an array of output lines.
     *
     * @return SSHCommandResultInterface
     */
    public function setOutput(array $lines): SSHCommandResultInterface
    {
        $this->sanitize($lines);

        $this->outputLines = $lines;

        return $this;
    }

    /**
     * Apply formatting before consuming the lines, e.g. trim the last
     * empty line.
     *
     * @param array $lines
     */
    protected function sanitize(array &$lines)
    {
        // remove the last empty line of output
        if (
            $this->getCommand()->getOption('output_trim_last_empty_line') &&
            empty(trim(end($lines)))
        ) {
            array_pop($lines);
        }
    }

    /**
     * Fluent setter for the stderr lines of the command.
     *
     * @param array $lines an array of output lines.
     *
     * @return SSHCommandResultInterface
     */
    public function setErrorOutput(array $lines): SSHCommandResultInterface
    {
        // remove the last empty line of output
        if (empty(trim(end($lines)))) {
            array_pop($lines);
        }

        $this->errorLines = $lines;

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
    public function getErrorOutput(bool $asString = false)
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
     * @return SSHCommandResultInterface
     */
    public function setOutputDelimiter(string $delim): SSHCommandResultInterface
    {
        $this->delimiter = $delim;

        return $this;
    }

    /**
     * Log the command output (debug level only)
     */
    public function logResult(): void
    {
        $outputLines = $this->getOutput();
        $errorLines  = $this->getErrorOutput();

        $headline = empty($outputLines)
            ? 'Command output was empty.'
            : 'Command returned:';

        $this->logMultilineOutput($headline, $outputLines);

        if (!empty($errorLines)) {
            $this->logMultilineOutput('Command STDERR:', $errorLines);
        }
    }

    /**
     * Log one title line and then each line of the passed array on a separate
     * log line.
     *
     * @param string $title
     * @param array  $lines
     */
    protected function logMultilineOutput(string $title, array $lines): void
    {
        $this->debug($title);
        foreach ($lines as $line) {
            $this->debug($line);
        }
        $this->debug('---');
    }
}

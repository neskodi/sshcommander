<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Neskodi\SSHCommander;

use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\LoggerAwareInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Traits\Loggable;
use DateTime;

class SSHCommandResult implements
    SSHCommandResultInterface,
    LoggerAwareInterface
{
    use Loggable;

    const STATUS_OK    = 'ok';
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
    protected $outputLines;

    /**
     * @var array
     */
    protected $errorLines;

    /**
     * @var float
     */
    protected $commandStartTime;

    /**
     * @var float
     */
    protected $commandEndTime;

    /**
     * @var float
     */
    protected $commandElapsedTime;

    /**
     * @var bool
     */
    protected $isTimeout;

    /**
     * @var bool
     */
    protected $isTimelimit;

    /**
     * CommandResult constructor.
     *
     * @param SSHCommandInterface $command     the command that was run
     * @param int|null            $exitCode    the exit code of the command
     * @param array|null          $outputLines the output lines
     * @param array|null          $errorLines  the error lines, if separate
     */
    public function __construct(
        SSHCommandInterface $command,
        ?int $exitCode = null,
        ?array $outputLines = null,
        ?array $errorLines = null
    ) {
        $this->setCommand($command);

        if (!is_null($exitCode)) {
            $this->setExitCode($exitCode);
        }

        if (!is_null($outputLines)) {
            $this->setOutput($outputLines);
        }

        if (!is_null($errorLines)) {
            $this->setErrorOutput($errorLines);
        }
    }

    /**
     * Fluent setter for the command object.
     *
     * @param SSHCommandInterface $command
     *
     * @return SSHCommandResultInterface
     */
    public function setCommand(
        SSHCommandInterface $command
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
    public function setExitCode(?int $code = null): SSHCommandResultInterface
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
            $this->getCommand()->getConfig('output_trim_last_empty_line') &&
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
        $this->sanitize($lines);

        $this->errorLines = $lines;

        return $this;
    }

    /**
     * Get the status of the command as 'ok' or 'error'.
     *
     * @return string
     */
    public function getStatus(): ?string
    {
        if (is_null($this->getExitCode())) {
            return null;
        }

        return $this->isOk() ? static::STATUS_OK : static::STATUS_ERROR;
    }

    /**
     * Get the exit code of the command as integer.
     *
     * @return int
     */
    public function getExitCode(): ?int
    {
        if (is_null($this->exitCode)) {
            return null;
        }

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
        if (is_null($this->outputLines)) {
            return null;
        }

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
        if (is_null($this->errorLines)) {
            return null;
        }

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
    public function isOk(): ?bool
    {
        if (is_null($this->getExitCode())) {
            return null;
        }

        return in_array(
            $this->getExitCode(),
            $this->getCommand()->getSuccessfulExitCodes()
        );
    }

    /**
     * Convenience method to tell if the command returned an error exit code.
     *
     * @return bool
     */
    public function isError(): ?bool
    {
        if (is_null($this->getExitCode())) {
            return null;
        }

        return !$this->isOk();
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
        $status      = $this->getStatus();
        $code        = $this->getExitCode();

        if ($this->isError()) {
            // error is logged on the notice level
            $this->notice(
                sprintf('Command returned error code: %d', $code)
            );
        } elseif ($this->isOk()) {
            // success is logged on the debug level only
            $this->debug(
                sprintf(
                    'Command returned exit status: %s (code %d)',
                    $status,
                    $code
                )
            );
        }

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

    /**
     * Register the command start time.
     *
     * @param DateTime $time
     *
     * @return SSHCommandResultInterface
     */
    public function setCommandStartTime(float $time): SSHCommandResultInterface
    {
        $this->commandStartTime = $time;

        return $this;
    }

    /**
     * Register the command end time.
     *
     * @param DateTime $time
     *
     * @return SSHCommandResultInterface
     */
    public function setCommandEndTime(float $time): SSHCommandResultInterface
    {
        $this->commandEndTime = $time;

        return $this;
    }

    /**
     * Register the command elapsed time.
     *
     * @param float $time
     *
     * @return SSHCommandResultInterface
     */
    public function setCommandElapsedTime(float $time): SSHCommandResultInterface
    {
        $this->commandElapsedTime = $time;

        return $this;
    }

    /**
     * Get the command start time.
     *
     * @return DateTime
     */
    public function getCommandStartTime(): ?float
    {
        return $this->commandStartTime;
    }

    /**
     * Get the command end time.
     *
     * @return DateTime
     */
    public function getCommandEndTime(): ?float
    {
        return $this->commandEndTime;
    }

    /**
     * Get the command elapsed time.
     *
     * @return float
     */
    public function getCommandElapsedTime(): ?float
    {
        return $this->commandElapsedTime;
    }

    /**
     * Register the fact whether the command execution resulted in a timeout.
     *
     * @param bool $isTimeout
     *
     * @return SSHCommandResultInterface
     */
    public function setIsTimeout(bool $isTimeout): SSHCommandResultInterface
    {
        $this->isTimeout = $isTimeout;

        return $this;
    }

    /**
     * Register the fact whether the command execution resulted in an exceeded
     * time limit.
     *
     * @param bool $isTimelimit
     *
     * @return SSHCommandResultInterface
     */
    public function setIsTimelimit(bool $isTimelimit): SSHCommandResultInterface
    {
        $this->isTimelimit = $isTimelimit;

        return $this;
    }

    /**
     * Check if the execution of command resulted in a timeout.
     *
     * @return bool|null
     */
    public function isTimeout(): ?bool
    {
        return $this->isTimeout;
    }

    /**
     * Check if the execution of command resulted in an exceeded time limit.
     *
     * @return bool|null
     */
    public function isTimelimit(): ?bool
    {
        return $this->isTimelimit;
    }
}

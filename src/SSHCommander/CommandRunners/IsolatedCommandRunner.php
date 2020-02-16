<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\CommandRunners\Decorators\CRPromptDetectionDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRTimeoutHandlerDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRErrorHandlerDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRConnectionDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRBasedirDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRCleanupDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRLoggerDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRResultDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRTimerDecorator;
use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\SSHConfig;

class IsolatedCommandRunner
    extends BaseCommandRunner
    implements SSHCommandRunnerInterface,
               DecoratedCommandRunnerInterface
{
    /**
     * Execute the command in the isolated shell and close this channel
     * immediately. Uses the exec() method of phpseclib's SSH2.
     *
     * @param SSHCommandInterface $command
     */
    public function executeOnConnection(SSHCommandInterface $command): void
    {
        $this->getConnection()->execIsolated($command);
    }

    public function withDecorators(): DecoratedCommandRunnerInterface
    {
        // Add command decorators
        // !! ORDER MATTERS !!
        return $this->with(CRTimerDecorator::class)
                    ->with(CRLoggerDecorator::class)
                    ->with(CRResultDecorator::class)
                    ->with(CRBasedirDecorator::class)
                    ->with(CRErrorHandlerDecorator::class)
                    ->with(CRTimeoutHandlerDecorator::class)
                    ->with(CRPromptDetectionDecorator::class)
                    ->with(CRCleanupDecorator::class)
                    ->with(CRConnectionDecorator::class);
    }

    /**
     * Get command's exit code.
     *
     * @param SSHCommandInterface $command
     *
     * @return int|null
     */
    public function getLastExitCode(SSHCommandInterface $command): ?int
    {
        return $this->getConnection()->getLastExitCode();
    }

    /**
     * Get command's output lines.
     *
     * @param SSHCommandInterface $command
     *
     * @return array
     */
    public function getStdOutLines(SSHCommandInterface $command): array
    {
        return $this->getConnection()->getStdOutLines();
    }

    /**
     * Get command's error lines.
     *
     * @param SSHCommandInterface $command
     *
     * @return array
     */
    public function getStdErrLines(SSHCommandInterface $command): array
    {
        if ($command->getConfig('separate_stderr')) {
            return $this->getConnection()->getStdErrLines();
        }

        return [];
    }

    /**
     * Error handling in the isolated runner works by prepending 'set -e'
     * command to the main command. This is equivalent to the error trap used by
     * the interactive runner, and will ensure that the shell will not execute
     * any subsequent commands following the one that resulted in error.
     *
     * @param SSHCommandInterface $command
     *
     * @noinspection PhpUnused
     */
    public function setupErrorHandler(SSHCommandInterface $command): void
    {
        if (SSHConfig::BREAK_ON_ERROR_ALWAYS === $command->getConfig('break_on_error')) {
            // turn on errexit mode
            $command->prependCommand('set -e');
        }
    }

    /**
     * If user's command needs to be executed in a separate directory, prepend
     * the 'cd' command to the main command.
     *
     * @param SSHCommandInterface $command
     *
     * @noinspection PhpUnused
     */
    public function setupBasedir(SSHCommandInterface $command): void
    {
        $basedir = $command->getConfig('basedir');

        if ($basedir && is_string($basedir)) {
            $basedirCommand = sprintf('cd %s', escapeshellarg($basedir));

            $command->prependCommand($basedirCommand);
        }
    }
}

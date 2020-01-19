<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\SSHConfig;

class IsolatedCommandRunner
    extends BaseCommandRunner
    implements SSHCommandRunnerInterface,
               DecoratedCommandRunnerInterface
{
    public function executeOnConnection(SSHCommandInterface $command): void
    {
        $this->getConnection()->execIsolated($command);
    }

    public function getLastExitCode(SSHCommandInterface $command): ?int
    {
        return $this->getConnection()->getLastExitCode();
    }

    public function getStdOutLines(SSHCommandInterface $command): array
    {
        return $this->getConnection()->getStdOutLines();
    }

    public function getStdErrLines(SSHCommandInterface $command): array
    {
        if ($command->getConfig('separate_stderr')) {
            return $this->getConnection()->getStdErrLines();
        }

        return [];
    }

    public function setupErrorHandler(SSHCommandInterface $command): void
    {
        if (SSHConfig::BREAK_ON_ERROR_ALWAYS === $command->getConfig('break_on_error')) {
            // turn on errexit mode
            $command->prependCommand('set -e');
        }
    }

    public function setupBasedir(SSHCommandInterface $command): void
    {
        $basedir = $command->getConfig('basedir');

        if ($basedir && is_string($basedir)) {
            $basedirCommand = sprintf('cd %s', escapeshellarg($basedir));

            $command->prependCommand($basedirCommand);
        }
    }
}

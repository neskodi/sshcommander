<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;

class IsolatedCommandRunner
    extends BaseCommandRunner
    implements SSHCommandRunnerInterface,
               DecoratedCommandRunnerInterface
{
    /**
     * Execute the command on the prepared connection.
     *
     * @param SSHCommandInterface $command
     */
    public function exec(SSHCommandInterface $command): void
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
}

<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Traits\HasConnection;
use Neskodi\SSHCommander\Traits\HasResult;

class IsolatedCommandRunner
    extends BaseCommandRunner
    implements SSHCommandRunnerInterface,
               DecoratedCommandRunnerInterface
{
    use HasConnection;
    use HasResult;

    /**
     * Execute the command on the prepared connection.
     *
     * @param SSHCommandInterface $command
     */
    public function exec(SSHCommandInterface $command): void
    {
        $this->getConnection()->execIsolated($command);
    }
}

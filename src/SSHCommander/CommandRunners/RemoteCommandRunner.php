<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\CommandRunners\Decorators\CRConnectionDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRLoggerDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRResultDecorator;
use Neskodi\SSHCommander\CommandRunners\Decorators\CRTimerDecorator;
use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHRemoteCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Traits\HasConnection;
use Neskodi\SSHCommander\Traits\HasResult;

class RemoteCommandRunner
    extends BaseCommandRunner
    implements SSHRemoteCommandRunnerInterface,
               DecoratedCommandRunnerInterface
{
    use HasConnection;
    use HasResult;

    /**
     * Run the command.
     *
     * @param SSHCommandInterface $command the object containing the command to
     *                                     run
     *
     * @return SSHCommandResultInterface
     */
    public function run(SSHCommandInterface $command): SSHCommandResultInterface
    {
        // Add command decorators and execute the command.
        // !! ORDER MATTERS !!
        $this->with(CRTimerDecorator::class)
             ->with(CRLoggerDecorator::class)
             ->with(CRResultDecorator::class)
             ->with(CRConnectionDecorator::class)

             ->exec($command);

        return $this->getResult();
    }

    /**
     * Wrap this command runner with a decorator.
     *
     * @param string $class
     *
     * @return DecoratedCommandRunnerInterface
     */
    public function with(string $class): DecoratedCommandRunnerInterface
    {
        return new $class($this);
    }

    /**
     * Execute the command on the prepared connection.
     *
     * @param SSHCommandInterface $command
     */
    public function exec(SSHCommandInterface $command): void
    {
        $this->getConnection()->exec($command);
    }
}

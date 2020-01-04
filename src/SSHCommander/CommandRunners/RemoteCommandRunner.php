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
use Neskodi\SSHCommander\SSHCommand;

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
        $prepared = $this->prepareCommand($command);

        // Add command decorators and execute the command.
        // !! ORDER MATTERS !!
        $this->with(CRTimerDecorator::class)
             ->with(CRLoggerDecorator::class)
             ->with(CRResultDecorator::class)
             ->with(CRConnectionDecorator::class)

             ->exec($prepared);

        return $this->getResult();
    }

    public function with(string $class): DecoratedCommandRunnerInterface
    {
        return new $class($this);
    }

    /**
     * Execute the command on the prepared connection.
     *
     * @param $command
     */
    public function exec(SSHCommandInterface $command): void
    {
        $this->getConnection()->exec($command);
    }

    /**
     * Prepend preliminary commands to the main command according to main
     * command configuration. If any preparation is necessary, such as moving
     * into basedir or setting the errexit option before running the main
     * command, another instance will be returned that contains the prepended
     * extra commands.
     *
     * @param SSHCommandInterface $command
     *
     * @return SSHCommandInterface
     */
    public function prepareCommand(SSHCommandInterface $command): SSHCommandInterface
    {
        if (
            !$command->getConfig('break_on_error') &&
            !$command->getConfig('basedir')
        ) {
            // no need to prepare
            return $command;
        }

        $prepared = new SSHCommand($command);

        if ($command->getConfig('basedir')) {
            $this->prependBasedir($prepared);
        }

        if ($command->getConfig('break_on_error')) {
            $this->setErrexitFlag($prepared);
        }

        return $prepared;
    }

    /**
     * Prepend 'cd basedir' to the command so it starts running in the directory
     * specified by user.
     *
     * @param SSHCommandInterface $command
     */
    protected function prependBasedir(SSHCommandInterface $command): void
    {
        $basedirCommand = sprintf('cd %s', $command->getConfig('basedir'));
        $command->prependCommand($basedirCommand);
    }

    /**
     * Set the errexit option to the shell, so that it stops running other
     * subcommands when some command in chain returns a non-zero exit code.
     *
     * @param SSHCommandInterface $command
     */
    protected function setErrexitFlag(SSHCommandInterface $command): void
    {
        $command->prependCommand('set -e');
    }
}

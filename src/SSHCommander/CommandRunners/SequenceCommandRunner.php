<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\SSHSequenceCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Traits\HasResultCollection;
use Neskodi\SSHCommander\SSHCommand;

class SequenceCommandRunner
    extends RemoteCommandRunner
    implements SSHSequenceCommandRunnerInterface
{
    use HasResultCollection;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * Run the command and save the result to the collection.
     *
     * @param SSHCommandInterface $command the object containing the command to
     *                                     run
     *
     * @return SSHCommandResultInterface
     */
    public function run(SSHCommandInterface $command): SSHCommandResultInterface
    {
        $result = parent::run($command);

        $this->saveResultToCollection($result);

        return $result;
    }

    protected function saveResultToCollection(SSHCommandResultInterface $result): void
    {
        $collection = $this->getResultCollection();

        $collection[] = $result;
    }

    /**
     * Execute the command on the prepared connection.
     *
     * @param SSHCommandInterface $command
     */
    public function exec(SSHCommandInterface $command): void
    {
        $this->getConnection()->execInteractive($command);
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
        $prepared = new SSHCommand($command);

        if ($command->getConfig('break_on_error')) {
            // set the errexit flag explicitly, because this command has
            // requested it and it might be previously unset
            $this->setErrexitFlag($prepared);
        } else {
            // set the errexit flag explicitly, because this command has
            // requested it and it might be previously set
            $this->unsetErrexitFlag($prepared);
        }

        return $prepared;
    }

    /**
     * Unset the errexit option on the shell explicitly, because it might be set
     * by previous commands.
     *
     * @param SSHCommandInterface $command
     */
    protected function unsetErrexitFlag(SSHCommandInterface $command): void
    {
        $command->prependCommand('set +e');
    }

    /**
     * Run the preliminary commands in the beginning of the sequence, such as
     * moving into basedir
     */
    public function initSequence(): void
    {
        $this->initBasedir();
    }

    protected function initBasedir(): void
    {
        if ($basedir = $this->getConfig('basedir')) {
            $command = sprintf('cd %s', $basedir);
            $command = new SSHCommand($command, $this->getConfig());

            $this->run($command);
        }
    }

    /**
     * Some global options, for instance 'basedir', should not propagate to
     * every command in the sequence.
     *
     * @param array $config
     */
    public function filterCommandOptionsBeforeRun(array &$config = []): void
    {
        unset($config['basedir']);
    }


    protected function skipConfigValidation(): bool
    {
        return true;
    }
}

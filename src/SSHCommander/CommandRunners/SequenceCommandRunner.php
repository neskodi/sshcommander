<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\SSHSequenceCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Traits\HasResultCollection;

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
}

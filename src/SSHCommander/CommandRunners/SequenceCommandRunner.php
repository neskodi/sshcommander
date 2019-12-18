<?php

namespace Neskodi\SSHCommander\CommandRunners;

use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHRemoteCommandRunnerInterface;

class SequenceCommandRunner
    extends RemoteCommandRunner
    implements SSHRemoteCommandRunnerInterface
{
    public function run(SSHCommandInterface $command): SSHCommandResultInterface
    {

    }
}

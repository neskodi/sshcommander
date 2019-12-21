<?php

namespace Neskodi\SSHCommander\Interfaces;

interface SSHSequenceCommandRunnerInterface extends SSHRemoteCommandRunnerInterface
{
    public function getResultCollection(): ?SSHResultCollectionInterface;
}

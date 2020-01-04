<?php

namespace Neskodi\SSHCommander\Interfaces;

interface SSHSequenceCommandRunnerInterface extends SSHRemoteCommandRunnerInterface
{
    public function getResultCollection(): ?SSHResultCollectionInterface;

    public function initSequence(): void;

    public function filterCommandOptionsBeforeRun(array &$config = []): void;
}

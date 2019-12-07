<?php

namespace Neskodi\SSHCommander\Interfaces;

interface SSHRemoteCommandRunnerInterface extends SSHCommandRunnerInterface
{
    public function setConnection(SSHConnectionInterface $connection): SSHRemoteCommandRunnerInterface;

    public function getConnection(?SSHCommandInterface $command = null): ?SSHConnectionInterface;
}

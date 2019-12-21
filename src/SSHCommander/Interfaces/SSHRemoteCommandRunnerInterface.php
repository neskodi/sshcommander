<?php

namespace Neskodi\SSHCommander\Interfaces;

interface SSHRemoteCommandRunnerInterface extends SSHCommandRunnerInterface
{
    public function setConnection(SSHConnectionInterface $connection);

    public function getConnection(): ?SSHConnectionInterface;
}

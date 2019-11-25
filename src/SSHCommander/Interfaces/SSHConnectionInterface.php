<?php

namespace Neskodi\SSHCommander\Interfaces;

use phpseclib\Net\SSH2;

interface SSHConnectionInterface
{
    public function authenticate();

    public function setConfig(SSHConfigInterface $config): SSHConnectionInterface;

    public function getConfig(): SSHConfigInterface;

    public function getSSH2(): SSH2;

    public function exec(CommandInterface $command): array;
}

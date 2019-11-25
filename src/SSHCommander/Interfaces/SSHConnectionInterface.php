<?php

namespace Neskodi\SSHCommander\Interfaces;

use phpseclib\Net\SSH2;
use Closure;

interface SSHConnectionInterface
{
    public function authenticate();

    public function setConfig(SSHConfigInterface $config): SSHConnectionInterface;

    public function getConfig(): SSHConfigInterface;

    public function getSSH2(): SSH2;

    public function exec(string $command, ?Closure $callback = null);
}

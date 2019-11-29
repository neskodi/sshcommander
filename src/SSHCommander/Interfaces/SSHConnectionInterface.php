<?php

namespace Neskodi\SSHCommander\Interfaces;

use phpseclib\Net\SSH2;
use Psr\Log\LoggerInterface;

interface SSHConnectionInterface
{
    public function authenticate(): bool;

    public function setConfig(SSHConfigInterface $config): SSHConnectionInterface;

    public function setTimeout(int $timeout): SSHConnectionInterface;

    public function getConfig(): SSHConfigInterface;

    public function getSSH2(): SSH2;

    public function exec(CommandInterface $command): CommandResultInterface;

    public function setLogger(LoggerInterface $logger);

    public function getLogger(): ?LoggerInterface;
}

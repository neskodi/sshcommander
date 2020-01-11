<?php /** @noinspection PhpUnused */

namespace Neskodi\SSHCommander\Interfaces;

interface SSHCommandRunnerInterface
{
    public function prepareCommand(SSHCommandInterface $command): SSHCommandInterface;

    public function exec(SSHCommandInterface $command): void;

    public function run(SSHCommandInterface $command);


    public function setConfig(SSHConfigInterface $config);

    public function getConfig(?string $param = null);

    public function set($param, $value = null): SSHCommandRunnerInterface;


    public function getResult(): ?SSHCommandResultInterface;

    public function setResult(SSHCommandResultInterface $result);


    public function getConnection(): ?SSHConnectionInterface;

    public function setConnection(SSHConnectionInterface $connection);
}

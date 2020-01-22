<?php /** @noinspection PhpUnused */

namespace Neskodi\SSHCommander\Interfaces;

interface SSHCommandRunnerInterface
{
    public function executeOnConnection(SSHCommandInterface $command): void;

    public function execDecorated(SSHCommandInterface $command): void;

    public function run(SSHCommandInterface $command);


    public function setConfig(SSHConfigInterface $config);

    public function getConfig(?string $param = null);

    public function set($param, $value = null);


    public function getResult(): ?SSHCommandResultInterface;

    public function setResult(SSHCommandResultInterface $result);


    public function getConnection(): ?SSHConnectionInterface;

    public function setConnection(SSHConnectionInterface $connection);
}

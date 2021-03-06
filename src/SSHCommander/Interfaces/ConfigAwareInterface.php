<?php

namespace Neskodi\SSHCommander\Interfaces;

interface ConfigAwareInterface
{
    public function setConfig(SSHConfigInterface $config);

    public function getConfig(?string $param = null);

    public function set($param, $value = null);
}

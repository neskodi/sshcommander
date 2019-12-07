<?php

namespace Neskodi\SSHCommander\Interfaces;

interface ConfigAwareInterface
{
    public function setConfig(SSHConfigInterface $config, bool $validateForConnection = true);

    public function getConfig(?string $param = null);
}

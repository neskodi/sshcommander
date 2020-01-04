<?php

namespace Neskodi\SSHCommander\Interfaces;

interface ConfigAwareInterface
{
    public function setConfig($config);

    public function mergeConfig($config, bool $missingOnly = false);

    public function setOption(string $key, $value);

    public function getConfig(?string $param = null);
}

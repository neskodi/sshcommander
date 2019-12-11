<?php

namespace Neskodi\SSHCommander\Tests\Mocks;

use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Tests\TestCase;
use Neskodi\SSHCommander\SSHConfig;

class MockSSHConfig extends SSHConfig implements SSHConfigInterface
{
    protected static $overrides = [];

    public function loadUserConfigFile(): SSHConfigInterface
    {
        $values = (new TestCase)->getTestConfigAsArray(
            TestCase::CONFIG_SECONDARY_ONLY
        );

        $values = array_merge($values, static::$overrides);

        $this->setFromArray($values);

        return $this;
    }

    public static function setOverrides(array $overrides): void
    {
        static::$overrides = $overrides;
    }

    public static function resetOverrides(): void
    {
        static::$overrides = [];
    }
}

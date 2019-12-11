<?php

namespace Neskodi\SSHCommander\Tests\Mocks;

use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\SSHConfig;

class MockSSHConfigMissingDefaultFile
    extends SSHConfig
    implements SSHConfigInterface
{
    /**
     * Let's return a non-existing default path so we can test that the relevant
     * exception is thrown
     *
     * @return string
     */
    public static function getDefaultConfigFileLocation(): string
    {
        return '/no/such/file';
    }
}

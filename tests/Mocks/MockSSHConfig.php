<?php

namespace Neskodi\SSHCommander\Tests\Mocks;

use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\SSHConfig;

class MockSSHConfig extends SSHConfig implements SSHConfigInterface
{
    public static function getDefaultConfigFileLocation(): string
    {
        return '/no/such/file';
    }
}

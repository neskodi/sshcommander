<?php

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Tests\IntegrationTestCase;

class ConnectionTest extends IntegrationTestCase
{
    /** @noinspection PhpUnhandledExceptionInspection */
    public function testFeatureInspection()
    {
        $commander = $this->getSSHCommander($this->sshOptions);

        $this->assertTrue($commander->getConnection()->supports('system_timeout'));
        $this->assertFalse($commander->getConnection()->supports('space_warp'));
    }
}

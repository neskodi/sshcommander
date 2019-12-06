<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Tests\TestCase;
use Neskodi\SSHCommander\SSHConnection;
use Neskodi\SSHCommander\SSHCommand;
use Neskodi\SSHCommander\SSHConfig;

class SSHConnectionTest extends TestCase
{
    public function setUp(): void
    {
        $this->buildSshOptions();
    }

    public function testExecWithLazyAuthentication()
    {
        $config = new SSHConfig($this->sshOptions);

        $config->set('autologin', false);

        $connection = new SSHConnection($config);

        $this->assertFalse($connection->isAuthenticated());

        $connection->exec(new SSHCommand('ls', $config->all()));

        $this->assertTrue($connection->isAuthenticated());
    }
}

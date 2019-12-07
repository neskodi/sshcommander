<?php /** @noinspection PhpUndefinedMethodInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\Unit;

use Neskodi\SSHCommander\CommandRunners\RemoteCommandRunner;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Tests\TestCase;
use Neskodi\SSHCommander\SSHConnection;
use Neskodi\SSHCommander\SSHCommand;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class RemoteCommandRunnerTest extends TestCase
{
    public function testConstructor(): void
    {
        $config = $this->getTestConfigAsObject();
        $logger = $this->getTestLogger(LogLevel::DEBUG);

        $runner = new RemoteCommandRunner($config, $logger);

        $this->assertInstanceOf(LoggerInterface::class, $runner->getLogger());
        $this->assertInstanceOf(SSHConfigInterface::class, $runner->getConfig());
    }

    public function testGetConnection(): void
    {
        $config = $this->getTestConfigAsObject();
        $logger = $this->getTestLogger(LogLevel::DEBUG);

        $runner = new RemoteCommandRunner($config, $logger);

        $this->assertNull($runner->getConnection());

        // now add a command
        $config->set('host', 'test');
        $command    = new SSHCommand('ls', $config);
        $connection = $runner->getConnection($command);

        $this->assertInstanceOf(
            SSHConnectionInterface::class,
            $connection
        );

        $this->assertEquals('test', $connection->getConfig('host'));
    }

    public function testSetConnection(): void
    {
        $config = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            [
                'host'      => 'test',
                'autologin' => false,
            ]
        );

        $logger     = $this->getTestLogger(LogLevel::DEBUG);
        $connection = new SSHConnection($config);

        $runner = new RemoteCommandRunner($config, $logger);
        $runner->setConnection($connection);

        $this->assertEquals(
            'test',
            $runner->getConnection()->getConfig('host')
        );
    }

    public function testRun(): void
    {
        $config     = $this->getTestConfigAsObject();
        $logger     = $this->getTestLogger(LogLevel::DEBUG);
        $connection = $this->getMockConnection();

        $runner = new RemoteCommandRunner($config, $logger);
        $runner->setConnection($connection);

        $result = $runner->run(new SSHCommand('ls', $config));

        $this->assertInstanceOf(SSHCommandResultInterface::class, $result);
    }
}

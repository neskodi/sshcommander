<?php /** @noinspection PhpUndefinedMethodInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\Unit;

use Neskodi\SSHCommander\CommandRunners\Decorators\CRTimeoutHandlerDecorator;
use Neskodi\SSHCommander\CommandRunners\InteractiveCommandRunner;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\CommandRunners\IsolatedCommandRunner;
use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Tests\Mocks\MockSSHConnection;
use Neskodi\SSHCommander\Tests\TestCase;
use Neskodi\SSHCommander\SSHConnection;
use Neskodi\SSHCommander\SSHCommand;
use Psr\Log\LoggerInterface;

class CommandRunnerTest extends TestCase
{
    public function testConstructor(): void
    {
        $config = $this->getTestConfigAsObject();
        $logger = $this->createTestLogger();

        $runner = new IsolatedCommandRunner($config, $logger);

        $this->assertInstanceOf(LoggerInterface::class, $runner->getLogger());
        $this->assertInstanceOf(SSHConfigInterface::class, $runner->getConfig());
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

        $logger     = $this->createTestLogger();
        $connection = new SSHConnection($config);

        $runner = new IsolatedCommandRunner($config, $logger);

        $this->assertNull($runner->getConnection());

        $runner->setConnection($connection);

        $connection = $runner->getConnection();

        $this->assertInstanceOf(
            SSHConnectionInterface::class,
            $connection
        );

        $this->assertEquals(
            'test',
            $runner->getConnection()->getConfig('host')
        );
    }

    public function testRunSuccess(): void
    {
        $config     = $this->getTestConfigAsObject();
        $logger     = $this->createTestLogger();
        $connection = $this->getMockConnection();

        $runner = new IsolatedCommandRunner($config, $logger);
        $runner->setConnection($connection);

        $result = $runner->run(new SSHCommand('ls', $config));

        $this->assertInstanceOf(SSHCommandResultInterface::class, $result);
        $this->assertTrue($result->isOk());
    }

    public function testRunError(): void
    {
        $config = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            ['autologin' => true]
        );
        $logger = $this->createTestLogger();

        // expect success for authentication
        MockSSHConnection::expect(MockSSHConnection::RESULT_SUCCESS);
        $connection = $this->getMockConnection($config);

        // but expect failure when running command
        MockSSHConnection::expect(MockSSHConnection::RESULT_ERROR);

        $runner = new IsolatedCommandRunner($config, $logger);
        $runner->setConnection($connection);

        $result = $runner->run(new SSHCommand('ls', $config));

        $this->assertInstanceOf(SSHCommandResultInterface::class, $result);
        $this->assertTrue($result->isError());
    }

    public function testWrapCommand()
    {
        $config    = $this->getTestConfigAsObject(self::CONFIG_FULL);
        $runner    = new InteractiveCommandRunner($config);
        $decorator = new CRTimeoutHandlerDecorator($runner);

        $command = new SSHCommand('ls', ['timeout' => 11]);
        $decorator->wrapCommandIntoTimeout($command);

        $result = $command->getCommand();

        $this->assertRegExp(
            '/timeout --preserve-status 11 `ps -p \\$\\$ -ocomm=` <<(\\w+)\\nls\\n\\1/s',
            $result
        );
    }
}

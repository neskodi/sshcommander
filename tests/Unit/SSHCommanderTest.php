<?php /** @noinspection PhpParamsInspection */
/** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnusedLocalVariableInspection */
/** @noinspection PhpIncludeInspection */

namespace Neskodi\SSHCommander\Tests\Unit;

use Neskodi\SSHCommander\Interfaces\SSHResultCollectionInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\CommandRunners\IsolatedCommandRunner;
use Neskodi\SSHCommander\Exceptions\InvalidConfigException;
use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Tests\Mocks\MockSSHConnection;
use Neskodi\SSHCommander\Tests\TestCase;
use Neskodi\SSHCommander\SSHConnection;
use Neskodi\SSHCommander\SSHCommander;
use Neskodi\SSHCommander\SSHCommand;
use Neskodi\SSHCommander\SSHConfig;
use Neskodi\SSHCommander\Utils;
use Psr\Log\LoggerInterface;
use stdClass;

class SSHCommanderTest extends TestCase
{
    public function testConstructor(): void
    {
        $testConfig = $this->getTestConfigAsArray();

        $commander    = new SSHCommander($testConfig);
        $resultConfig = $commander->getConfig()->all();

        $this->assertEquals($testConfig, $resultConfig);
    }

    public function testConstructorWithInvalidConfig(): void
    {
        $this->expectException(InvalidConfigException::class);

        $commander = new SSHCommander(new stdClass);
    }

    public function testConstructorWithLogger(): void
    {
        $config = $this->getTestConfigAsArray();
        $logger = $this->createTestLogger();

        $commander = new SSHCommander($config, $logger);

        $this->assertInstanceOf(LoggerInterface::class, $commander->getLogger());
    }

    public function testConstructorConfigWithWriteableLogFile(): void
    {
        $file = $_ENV['ssh_log_file'];
        $this->assertTrue(Utils::isWritableOrCreatable($file));
        $config = $this->getTestConfigAsArray(self::CONFIG_FULL, [
            'log_file' => $file,
        ]);

        $commander = new SSHCommander($config);

        $this->assertInstanceOf(LoggerInterface::class, $commander->getLogger());
    }

    public function testSetConnection(): void
    {
        $testConfigConn = $this->getTestConfigAsArray(
            self::CONFIG_FULL,
            ['autologin' => false]
        );
        $testConfigComm = $this->getTestConfigAsArray(
            self::CONFIG_CONNECTION_ONLY,
            ['autologin' => false]
        );

        // create a connection with valid host
        $connection = new SSHConnection(new SSHConfig($testConfigConn));

        // create a commander using invalid host
        $testConfigComm['host'] = '********';

        $commander = new SSHCommander($testConfigComm);

        // inject valid connection object to the commander
        $commander->setConnection($connection);

        // test that commander's connection has valid config
        $resultConfig = $commander->getConnection()->getConfig();

        $this->assertEquals(
            $testConfigConn['host'],
            $resultConfig->getHost()
        );
    }

    public function testGetConnection(): void
    {
        $testConfigComm = $this->getTestConfigAsArray(
            self::CONFIG_CONNECTION_ONLY,
            ['autologin' => false]
        );

        $commander = new SSHCommander($testConfigComm);

        $connection = $commander->getConnection();

        $this->assertInstanceOf(SSHConnectionInterface::class, $connection);
    }

    public function testSetCommandRunner(): void
    {
        $testConfigA = $this->getTestConfigAsArray(
            TestCase::CONFIG_FULL,
            ['host' => 'hostA']
        );

        $testConfigB = $this->getTestConfigAsArray(
            TestCase::CONFIG_FULL,
            ['host' => 'hostB']
        );

        $commander = new SSHCommander($testConfigA);
        $runner    = new IsolatedCommandRunner($testConfigB);

        $commander->setInteractiveCommandRunner($runner);

        $host = $commander->getInteractiveCommandRunner()->getConfig('host');

        $this->assertEquals($host, 'hostB');
    }

    public function testSetConfig(): void
    {
        $testHost = '********';

        $testConfigA = new SSHConfig($this->getTestConfigAsArray());
        $testConfigB = new SSHConfig($this->getTestConfigAsArray(
            TestCase::CONFIG_FULL,
            ['host' => $testHost]
        ));
        $commander   = new SSHCommander($testConfigA);
        $commander->setConfig($testConfigB);

        $this->assertEquals($commander->getConfig('host'), $testHost);
    }

    public function testSetConfigFile(): void
    {
        SSHCommander::setConfigFile($this->getTestConfigFile());
        $testConfig = $this->getTestConfigAsArray();

        $commander = new SSHCommander([
            'host'     => 'example.com',
            'user'     => 'foo',
            'password' => 'bar',
        ]);

        $this->assertEquals(
            $commander->getConfig('delimiter_join_input'),
            $testConfig['delimiter_join_input']
        );

        // reset for further tests
        SSHConfig::resetConfigFileLocation();
    }

    public function testCreateCommand(): void
    {
        $testConfig = $this->getTestConfigAsArray();
        $strcmd     = 'pwd';

        $commander = new SSHCommander($testConfig);

        $command = $commander->createCommand('pwd', ['timeout' => 120]);

        $this->assertEquals($command->getConfig('timeout'), 120);
        $this->assertEquals($command->getConfig('host'), $testConfig['host']);
        $this->assertEquals($command->getCommand(), $strcmd);
    }

    public function testCreateCommandFromCommand(): void
    {
        $config = $this->getTestConfigAsArray();
        $strcmd = 'pwd';

        $commander = new SSHCommander($config);
        $commandA  = new SSHCommand($strcmd, ['timeout' => 120]);
        $commandB  = $commander->createCommand($commandA);

        // test that config options from commandA and default config were
        // properly merged
        $this->assertEquals($commandB->getConfig('timeout'), 120);
        $this->assertEquals($commandB->getConfig('host'), $config['host']);
        $this->assertEquals($commandB->getCommand(), $strcmd);
    }

    public function testDefaultConfigurationIsUsedByDefault(): void
    {
        $vendorConfig = (array)include(SSHConfig::getDefaultConfigFileLocation());
        $extra         = [
            'host'      => 'example.com',
            'user'      => 'foo',
            'password'  => 'bar',
            'autologin' => false,
        ];
        $mergedConfig = array_merge($vendorConfig, $extra);

        // we are providing only extra to the commander
        $commander = new SSHCommander($extra);

        $resultConfig = $commander->getConfig()->all();

        // and we expect the rest of values to be picked up
        $this->assertEquals($mergedConfig, $resultConfig);
    }

    public function testUserCanSetConfigurationAsFile(): void
    {
        SSHCommander::setConfigFile($this->getTestConfigFile());
        $extra         = [
            'host'      => 'example.com',
            'user'      => 'foo',
            'password'  => 'bar',
            'autologin' => false,
        ];

        $testConfig = $this->getTestConfigAsArray();
        $testConfig = array_merge($testConfig, $extra);

        // we are providing only extra to the commander
        $commander = new SSHCommander($extra);

        // and we expect the rest of values to be picked up
        $resultConfig = $commander->getConfig()->all();
        $this->assertEquals($testConfig, $resultConfig);

        // reset for further tests
        SSHConfig::resetConfigFileLocation();
    }

    public function testUserCanSetConfigurationAsArgument(): void
    {
        $testConfig = $this->getTestConfigAsArray();
        $extra      = [
            'host'      => 'example.com',
            'user'      => 'foo',
            'autologin' => false,
        ];
        $testConfig = array_merge($testConfig, $extra);

        $commander = new SSHCommander($testConfig);

        $resultConfig = $commander->getConfig()->all();

        $this->assertEquals($testConfig, $resultConfig);
    }

    public function testRunCommand(): void
    {
        $config    = $this->getTestConfigAsArray();
        $commander = $this->getSSHCommander($config);

        MockSSHConnection::expect(MockSSHConnection::RESULT_SUCCESS);
        $commander->setConnection($this->getMockConnection());

        $result = $commander->run('ls');

        $this->assertInstanceOf(SSHCommandResultInterface::class, $result);
        $this->assertTrue($result->isOk());
    }

    public function testRunCommandIsolated(): void
    {
        $config    = $this->getTestConfigAsArray();
        $commander = $this->getSSHCommander($config);

        MockSSHConnection::expect(MockSSHConnection::RESULT_SUCCESS);
        $commander->setConnection($this->getMockConnection());

        $result = $commander->runIsolated('ls');

        $this->assertInstanceOf(SSHCommandResultInterface::class, $result);
        $this->assertTrue($result->isOk());
    }

    public function testResultCollection(): void
    {
        $config    = $this->getTestConfigAsArray(self::CONFIG_FULL, [
            'basedir' => null,
        ]);

        $commander = $this->getSSHCommander($config);

        MockSSHConnection::expect(MockSSHConnection::RESULT_SUCCESS);
        $commander->setConnection($this->getMockConnection());

        $commander->runIsolated('ls');

        $collection = $commander->getResultCollection();
        $this->assertInstanceOf(SSHResultCollectionInterface::class, $collection);
        $this->assertEquals(1, $collection->count());

        $result = $collection->last();

        $this->assertInstanceOf(SSHCommandResultInterface::class, $result);
        $this->assertTrue($result->isOk());
        $this->assertEquals('ls', (string)$result->getCommand());
    }
}

<?php /** @noinspection PhpUndefinedMethodInspection */

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnusedLocalVariableInspection */
/** @noinspection PhpIncludeInspection */

namespace Neskodi\SSHCommander\Tests\Unit;

use Neskodi\SSHCommander\CommandRunners\RemoteCommandRunner;
use Neskodi\SSHCommander\Tests\TestCase;
use Neskodi\SSHCommander\SSHConnection;
use Neskodi\SSHCommander\SSHCommander;
use Neskodi\SSHCommander\SSHConfig;

class SSHCommanderTest extends TestCase
{
    public function testConstructor(): void
    {
        $testConfig = $this->getTestConfigAsArray();

        $commander    = new SSHCommander($testConfig);
        $resultConfig = $commander->getConfig()->all();

        $this->assertEquals($testConfig, $resultConfig);
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
        $commander              = new SSHCommander($testConfigComm);

        // inject valid connection object to the commander
        $commander->setConnection($connection);

        // test that commander's connection has valid config
        $resultConfig = $commander->getConnection()->getConfig();

        $this->assertEquals(
            $testConfigConn['host'],
            $resultConfig->getHost()
        );
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
        $runner    = new RemoteCommandRunner($testConfigB);

        $commander->setCommandRunner($runner);

        $host = $commander->getCommandRunner()->getConfig('host');

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
            'host' => '********',
            'user' => '********',
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

        $command = $commander->createCommand('pwd', ['timeout_command' => 120]);

        $this->assertEquals($command->getOption('timeout_command'), 120);
        $this->assertEquals($command->getOption('host'), $testConfig['host']);
        $this->assertEquals($command->getCommands(true, false), $strcmd);
    }
}

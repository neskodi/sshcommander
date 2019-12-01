<?php /** @noinspection ALL */
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
    public function testConstructor()
    {
        $testConfig = $this->getTestConfigAsArray();

        $commander    = new SSHCommander($testConfig);
        $resultConfig = $commander->getConfig()->all();

        $this->assertEquals($testConfig, $resultConfig);
    }

    public function testSetConnection()
    {
        $testConfigConn = $this->getTestConfigAsArray();
        $testConfigComm = $this->getTestConfigAsArray(
            TestCase::CONFIG_CONNECTION_ONLY
        );

        // create a connection with valid host
        $connection = new SSHConnection(new SSHConfig($testConfigConn));

        // create a commander using invalid host
        $testConfigComm['host'] = '********';
        $commander              = new SSHCommander($testConfigComm);

        // inject connection object to the commander
        $commander->setConnection($connection);

        // test that commander's connection has valid config
        $resultConfig = $commander->getConnection()->getConfig();

        $this->assertEquals(
            $testConfigConn['host'],
            $resultConfig->getHost()
        );
    }

    public function testSetCommandRunner()
    {
        $testConfigA = $this->getTestConfigAsArray(
            TestCase::CONFIG_FULL,
            ['host' => 'hostA']
        );

        $testConfigB = $this->getTestConfigAsArray(
            TestCase::CONFIG_FULL,
            ['host' => 'hostB']
        );

        $commanderA = new SSHCommander($testConfigA);
        $commanderB = new SSHCommander($testConfigB);

        $runnerA = new RemoteCommandRunner($commanderA);
        $runnerB = new RemoteCommandRunner($commanderB);

        $commanderB->setCommandRunner($runnerA);
        $host = $commanderB->getCommandRunner()
                           ->getCommander()
                           ->getConfig('host');

        $this->assertEquals($host, 'hostA');
    }

    public function testSetConfig()
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

    public function testSetConfigFile()
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

    public function testCreateCommand()
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

<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Interfaces\SSHCommanderInterface;
use Neskodi\SSHCommander\Tests\IntegrationTestCase;
use Neskodi\SSHCommander\SSHCommander;
use Neskodi\SSHCommander\Traits\Timer;
use Neskodi\SSHCommander\SSHConfig;
use Monolog\Handler\TestHandler;
use Psr\Log\LogLevel;
use RuntimeException;

class ConfigurationTest extends IntegrationTestCase
{
    use Timer;

    /***** CONFIGURATION PROPAGATION TESTS *****/

    // public function testPropagationFromConfigFile()
    // {
    //
    // }
    //
    // public function testPropagationFromGlobalConfig()
    // {
    //
    // }
    //
    // public function testPropagationFromCommandConfig()
    // {
    //
    // }

    /***** CREDENTIAL SELECTION TESTS *****/

    public function testConfigSelectsKey(): void
    {
        try {
            $this->requireUser();
            $this->requireKeyfile();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = array_merge($this->sshOptions, [
            'key' => file_get_contents($this->sshOptions['keyfile']),
        ]);
        $logger = $this->getTestableLogger(LogLevel::DEBUG);

        $commander = new SSHCommander($config, $logger);

        $result = $commander->run('pwd');

        $this->assertTrue($result->isOk());

        /** @var TestHandler $handler */
        $handler = $logger->popHandler();

        $this->assertTrue($handler->hasInfoThatMatches(
            '/Authenticating .*? with a private key/i'
        ));

        $this->assertTrue($handler->hasDebugThatContains(
            'SSH key is provided at runtime'
        ));

        $this->assertFalse($handler->hasDebugThatContains(
            'SSH key is loaded from file'
        ));
    }

    public function testConfigSelectsKeyfile(): void
    {
        try {
            $this->requireUser();
            $this->requireKeyfile();
            $this->requirePassword();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = array_merge($this->sshOptions, ['key' => null]);

        $logger = $this->getTestableLogger(LogLevel::DEBUG);

        $commander = new SSHCommander($config, $logger);

        $result = $commander->run('pwd');

        $this->assertTrue($result->isOk());

        /** @var TestHandler $handler */
        $handler = $logger->popHandler();

        $this->assertTrue($handler->hasInfoThatMatches(
            '/Authenticating .*? with a private key/i'
        ));

        $this->assertFalse($handler->hasDebugThatContains(
            'SSH key is provided at runtime'
        ));

        $this->assertTrue($handler->hasDebugThatContains(
            'SSH key is loaded from file'
        ));
    }

    public function testConfigSelectsPassword(): void
    {
        try {
            $this->requireUser();
            $this->requirePassword();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = array_merge($this->sshOptions, [
            'key'     => null,
            'keyfile' => null,
        ]);

        $logger = $this->getTestableLogger(LogLevel::DEBUG);

        $commander = new SSHCommander($config, $logger);

        $result = $commander->run('pwd');

        $this->assertTrue($result->isOk());

        /** @var TestHandler $handler */
        $handler = $logger->popHandler();

        $this->assertTrue($handler->hasInfoThatMatches(
            '/Authenticating .*? with a password/i'
        ));

        $this->assertFalse($handler->hasDebugThatContains(
            'Key contents provided via configuration'
        ));

        $this->assertFalse($handler->hasDebugThatContains(
            'Reading key contents from file'
        ));

        $this->assertTrue($handler->hasDebugThatContains(
            'SSH password is provided at runtime'
        ));
    }

    /***** TIMEOUT TESTS *****/

    public function testCommandTimeoutFromGlobalConfig(): void
    {
        $timeoutValue = 2;

        $config = array_merge(
            $this->sshOptions,
            [
                'timelimit'          => $timeoutValue,
                'timelimit_behavior' => SSHConfig::SIGNAL_TERMINATE,
            ]
        );

        $commander = $this->getSSHCommander($config);
        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->run('ping 127.0.0.1');

        $this->assertEquals(
        // an extra second for cleaning the command buffer
        // TODO: work around this by putting jobs to background
            $timeoutValue + 1,
            (int)$result->getCommandElapsedTime()
        );
    }

    public function testCommandTimeoutInCommandConfig(): void
    {
        $timeoutValue  = 2;
        $timeoutConfig = [
            'timelimit'          => $timeoutValue,
            'timelimit_behavior' => SSHConfig::SIGNAL_TERMINATE,
        ];

        $commander = $this->getSSHCommander($this->sshOptions);
        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->run('ping 127.0.0.1', $timeoutConfig);

        $this->assertEquals(
        // an extra second for cleaning the command buffer
        // TODO: work around this by putting jobs to background
            $timeoutValue + 1,
            (int)$result->getCommandElapsedTime()
        );
    }

    /***** BASEDIR TESTS *****/

    public function testBasedirFromGlobalConfig(): void
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $basedir = '/usr';
        $tested  = ['basedir' => $basedir];
        $config  = array_merge($this->sshOptions, $tested);

        // check that basedir picked for this test differs from default
        $defaultConfig = $this->getTestConfigAsArray();
        $this->assertNotEquals($defaultConfig['basedir'], $basedir);

        $commander = $this->getSSHCommander($config);

        $outputLines = $commander->run('pwd')->getOutput();

        $this->assertContains($basedir, $outputLines);
    }

    public function testBasedirInCommandConfig(): void
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $basedir = '/usr';
        $tested  = ['basedir' => $basedir];

        // check that basedir picked for this test differs from default
        $defaultConfig = $this->getTestConfigAsArray();
        $this->assertNotEquals($defaultConfig['basedir'], $basedir);

        $commander = $this->getSSHCommander($this->sshOptions);

        $outputLines = $commander->run('pwd', $tested)->getOutput();

        $this->assertContains($basedir, $outputLines);
    }

    // public function testSetBasedirOnTheFly(): void
    // {
    //
    // }


    /***** END CONFIG VALUE TESTS *****/

    /**
     * Run the command and return an array of output lines.
     *
     * @param SSHCommanderInterface $commander
     * @param array                 $commandConfig
     *
     * @return array
     */
    protected function runCompoundCommandWithErrorInTheMiddle(
        SSHCommanderInterface $commander,
        array $commandConfig = []
    ): array {
        $command = "echo 'A';echo 'B';cd /no/such/dir;echo 'C';echo 'D'";

        return $commander->run($command, $commandConfig)->getOutput();
    }

    protected function checkContainsErrorOutput(array $lines): void
    {
        $errorLines = array_filter($lines, function ($line) {
            return false !== stripos($line, 'no such');
        });

        $this->assertNotEmpty($errorLines);
    }

    protected function checkContainsPostErrorOutput(array $lines): void
    {
        $this->assertContains('C', $lines);
        $this->assertContains('D', $lines);
    }

    protected function checkNotContainsPostErrorOutput(array $lines): void
    {
        $this->assertNotContains('C', $lines);
        $this->assertNotContains('D', $lines);
    }

    protected function checkThatTestedConfigHasPropagated(
        SSHCommanderInterface $commander,
        array $tested
    ): void {
        foreach ($tested as $key => $value) {
            $this->assertSame($value, $commander->getConfig($key));
        }
    }
}

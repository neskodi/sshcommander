<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Interfaces\SSHCommanderInterface;
use Neskodi\SSHCommander\Tests\IntegrationTestCase;
use Neskodi\SSHCommander\Tests\Mocks\MockSSHConfig;
use Neskodi\SSHCommander\SSHCommander;
use Neskodi\SSHCommander\Traits\Timer;
use Monolog\Handler\TestHandler;
use Psr\Log\LogLevel;
use RuntimeException;

class SSHConfigTest extends IntegrationTestCase
{
    use Timer;

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
        $logger = $this->getTestLogger(LogLevel::DEBUG);

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

        $logger = $this->getTestLogger(LogLevel::DEBUG);

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

        $logger = $this->getTestLogger(LogLevel::DEBUG);

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

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testCommandTimeoutFromConfigFile(): void
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $timeoutValue  = 2;
        $timeoutConfig = ['timeout_command' => $timeoutValue];

        MockSSHConfig::setOverrides($timeoutConfig);
        $config    = new MockSSHConfig($this->sshOptions);
        $commander = new SSHCommander($config);

        $this->assertEquals($timeoutValue, $commander->getConfig('timeout_command'));

        $this->startTimer();
        $commander->run('ping google.com', $timeoutConfig);
        $elapsed = $this->stopTimer();

        $this->assertEquals($timeoutValue, (int)$elapsed);

        MockSSHConfig::resetOverrides();
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testCommandTimeoutFromGlobalConfig(): void
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $timeoutValue  = 2;
        $timeoutConfig = ['timeout_command' => $timeoutValue];
        $config        = array_merge($this->sshOptions, $timeoutConfig);

        $commander = new SSHCommander($config);

        $this->startTimer();
        $commander->run('ping google.com');
        $elapsed = $this->stopTimer();

        $this->assertEquals($timeoutValue, (int)$elapsed);
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testCommandTimeoutInCommandConfig(): void
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $timeoutValue  = 2;
        $timeoutConfig = ['timeout_command' => $timeoutValue];

        $commander = new SSHCommander($this->sshOptions);

        $this->startTimer();
        $commander->run('ping google.com', $timeoutConfig);
        $elapsed = $this->stopTimer();

        $this->assertEquals($timeoutValue, (int)$elapsed);
    }

    /***** BREAK ON ERROR TESTS *****/

    public function testNotBreakOnErrorFromConfigFile(): void
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $tested = [
            'break_on_error'  => false,
            'separate_stderr' => false,
            'suppress_stderr' => false,
        ];

        MockSSHConfig::setOverrides($tested);
        $config = new MockSSHConfig($this->sshOptions);

        $commander = new SSHCommander($config);

        $this->checkThatTestedConfigHasPropagated($commander, $tested);

        $outputLines = $this->runCompoundCommandWithErrorInTheMiddle(
            $commander
        );

        $this->checkContainsErrorOutput($outputLines);
        $this->checkContainsPostErrorOutput($outputLines);

        MockSSHConfig::resetOverrides();
    }

    public function testNotBreakOnErrorFromGlobalConfig(): void
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $tested = [
            'break_on_error'  => false,
            'separate_stderr' => false,
            'suppress_stderr' => false,
        ];
        $config = array_merge($this->sshOptions, $tested);

        $commander = new SSHCommander($config);

        $this->checkThatTestedConfigHasPropagated($commander, $tested);

        $outputLines = $this->runCompoundCommandWithErrorInTheMiddle(
            $commander
        );

        $this->checkContainsErrorOutput($outputLines);
        $this->checkContainsPostErrorOutput($outputLines);
    }

    public function testNotBreakOnErrorInCommandConfig(): void
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $commander = new SSHCommander($this->sshOptions);

        $outputLines = $this->runCompoundCommandWithErrorInTheMiddle(
            $commander,
            [
                'break_on_error'  => false,
                'separate_stderr' => false,
                'suppress_stderr' => false,
            ]
        );

        $this->checkContainsErrorOutput($outputLines);
        $this->checkContainsPostErrorOutput($outputLines);
    }

    /** @noinspection DuplicatedCode */
    public function testBreakOnErrorFromConfigFile(): void
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $tested = [
            'break_on_error'  => true,
            'separate_stderr' => false,
            'suppress_stderr' => false,
        ];

        MockSSHConfig::setOverrides($tested);
        $config = new MockSSHConfig($this->sshOptions);

        $commander = new SSHCommander($config);

        $this->checkThatTestedConfigHasPropagated($commander, $tested);

        $outputLines = $this->runCompoundCommandWithErrorInTheMiddle(
            $commander
        );

        $this->checkContainsErrorOutput($outputLines);
        $this->checkNotContainsPostErrorOutput($outputLines);

        MockSSHConfig::resetOverrides();
    }

    public function testBreakOnErrorFromGlobalConfig(): void
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $tested = [
            'break_on_error'  => true,
            'separate_stderr' => false,
            'suppress_stderr' => false,
        ];
        $config = array_merge($this->sshOptions, $tested);

        $commander = new SSHCommander($config);

        $this->checkThatTestedConfigHasPropagated($commander, $tested);

        $outputLines = $this->runCompoundCommandWithErrorInTheMiddle(
            $commander
        );

        $this->checkContainsErrorOutput($outputLines);
        $this->checkNotContainsPostErrorOutput($outputLines);
    }

    public function testBreakOnErrorInCommandConfig(): void
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $commander = new SSHCommander($this->sshOptions);

        $outputLines = $this->runCompoundCommandWithErrorInTheMiddle(
            $commander,
            [
                'break_on_error'  => true,
                'separate_stderr' => false,
                'suppress_stderr' => false,
            ]
        );

        $this->checkContainsErrorOutput($outputLines);
        $this->checkNotContainsPostErrorOutput($outputLines);
    }

    /***** BASEDIR TESTS *****/

    public function testBasedirFromConfigFile(): void
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $basedir = '/usr';
        $tested  = ['basedir' => $basedir];

        MockSSHConfig::setOverrides($tested);

        // check that basedir picked for this test differs from default
        $defaultConfig = $this->getTestConfigAsArray();
        $this->assertNotEquals($defaultConfig['basedir'], $basedir);

        $config    = new MockSSHConfig($this->sshOptions);
        $commander = new SSHCommander($config);

        $this->assertEquals($basedir, $commander->getConfig('basedir'));

        $outputLines = $commander->run('pwd')->getOutput();

        $this->assertContains($basedir, $outputLines);

        MockSSHConfig::resetOverrides();
    }

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

        $commander = new SSHCommander($config);

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

        $commander = new SSHCommander($this->sshOptions);

        $outputLines = $commander->run('pwd', $tested)->getOutput();

        $this->assertContains($basedir, $outputLines);
    }


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

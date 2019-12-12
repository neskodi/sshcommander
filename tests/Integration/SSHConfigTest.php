<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\Integration;

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
            'Key contents provided via configuration'
        ));

        $this->assertFalse($handler->hasDebugThatContains(
            'Reading key contents from file'
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
            'Key contents provided via configuration'
        ));

        $this->assertTrue($handler->hasDebugThatContains(
            'Reading key contents from file'
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
    }

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
}

<?php

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Tests\IntegrationTestCase;
use Neskodi\SSHCommander\Tests\Mocks\MockSSHConfig;
use Neskodi\SSHCommander\SSHCommander;
use Neskodi\SSHCommander\Traits\Timer;
use Neskodi\SSHCommander\SSHConfig;
use Psr\Log\LogLevel;

class SSHConfigTest extends IntegrationTestCase
{
    use Timer;

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testCommandTimeoutFromGlobalConfig(): void
    {
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
    public function testCommandTimeoutAtRunTime(): void
    {
        $timeoutValue  = 2;
        $timeoutConfig = ['timeout_command' => $timeoutValue];

        $commander = new SSHCommander($this->sshOptions);

        $this->startTimer();
        $commander->run('ping google.com', $timeoutConfig);
        $elapsed = $this->stopTimer();

        $this->assertEquals($timeoutValue, (int)$elapsed);
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testCommandTimeoutFromConfigFile(): void
    {
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

    public function testConfigSelectsKey(): void
    {

    }

    public function testConfigSelectsKeyfile(): void
    {

    }

    public function testConfigSelectsPassword(): void
    {

    }

}

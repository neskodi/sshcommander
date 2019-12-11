<?php

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Tests\IntegrationTestCase;
use Neskodi\SSHCommander\SSHCommander;
use Neskodi\SSHCommander\Traits\Timer;
use Neskodi\SSHCommander\SSHConfig;
use Psr\Log\LogLevel;


class SSHConfigTest extends IntegrationTestCase
{
    use Timer;

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testCommandTimeoutFromGlobalConfig()
    {
        $timeoutValue = 2;
        $timeoutConfig = ['timeout_command' => $timeoutValue];
        $config = array_merge($this->sshOptions, $timeoutConfig);

        $commander = new SSHCommander($config);

        $this->startTimer();
        $commander->run('ping google.com');
        $elapsed = $this->stopTimer();

        $this->assertEquals((int)$elapsed, $timeoutValue);
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testCommandTimeoutAtRunTime()
    {
        $timeoutValue = 2;
        $timeoutConfig = ['timeout_command' => $timeoutValue];

        $commander = new SSHCommander($this->sshOptions);

        $this->startTimer();
        $commander->run('ping google.com', $timeoutConfig);
        $elapsed = $this->stopTimer();

        $this->assertEquals((int)$elapsed, $timeoutValue);
    }
}

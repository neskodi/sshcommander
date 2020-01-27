<?php /** @noinspection PhpUnhandledExceptionInspection */

/** @noinspection DuplicatedCode */

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Tests\IntegrationTestCase;
use Neskodi\SSHCommander\SSHConfig;
use Monolog\Handler\TestHandler;
use Psr\Log\LogLevel;

class TimeoutTest extends IntegrationTestCase
{
    public function testIsolatedSetTimeoutFromGlobalConfig(): void
    {
        $timeoutValue = 2;
        $behavior     = SSHConfig::SIGNAL_TERMINATE;
        $command      = 'tail -f /etc/hostname';

        $config = array_merge(
            $this->sshOptions,
            [
                'timeout_command'  => $timeoutValue,
                'timeout_behavior' => $behavior,
            ]
        );

        $commander = $this->getSSHCommander($config);

        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->runIsolated($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertFalse($result->isTimelimit());
    }

    public function testIsolatedSetTimeoutFromCommandConfig(): void
    {
        $timeoutValue = 2;
        $behavior     = SSHConfig::SIGNAL_TERMINATE;
        $command      = 'tail -f /etc/hostname';

        $config = [
            'timeout_command'  => $timeoutValue,
            'timeout_behavior' => $behavior,
        ];

        $commander = $this->getSSHCommander($this->sshOptions);

        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->runIsolated($command, $config);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertFalse($result->isTimelimit());
    }

    public function testIsolatedSetTimeoutOnTheFly(): void
    {
        $timeoutValue = 2;
        $behavior     = SSHConfig::SIGNAL_TERMINATE;
        $command      = 'tail -f /etc/hostname';

        $commander = $this->getSSHCommander($this->sshOptions);
        $this->assertTrue($commander->getConnection()->isAuthenticated());

        // set timeout on the fly
        $commander->timeout($timeoutValue, $behavior);

        $result = $commander->runIsolated($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertFalse($result->isTimelimit());
    }

    public function testInteractiveSetTimeoutFromGlobalConfig(): void
    {
        $timeoutValue = 2;
        $behavior     = SSHConfig::SIGNAL_TERMINATE;
        $command      = 'tail -f /etc/hostname';

        $config = array_merge(
            $this->sshOptions,
            [
                'timeout_command'  => $timeoutValue,
                'timeout_behavior' => $behavior,
            ]
        );

        $commander = $this->getSSHCommander($config);

        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->run($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertFalse($result->isTimelimit());

        // check that the behavior was executed
        /** @var TestHandler $handler */
        $handler = $commander->getLogger()->popHandler();
        $regex   = sprintf('/^WRITE: %s$/', $behavior);
        $this->assertTrue($handler->hasRecordThatMatches($regex, LogLevel::DEBUG));
    }

    public function testInteractiveSetTimeoutFromCommandConfig(): void
    {
        $timeoutValue = 2;
        $behavior     = SSHConfig::SIGNAL_TERMINATE;
        $command      = 'tail -f /etc/hostname';

        $config = [
            'timeout_command'  => $timeoutValue,
            'timeout_behavior' => $behavior,
        ];

        $commander = $this->getSSHCommander($this->sshOptions);

        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->run($command, $config);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertFalse($result->isTimelimit());

        // check that the behavior was executed
        /** @var TestHandler $handler */
        $handler = $commander->getLogger()->popHandler();
        $regex   = sprintf('/^WRITE: %s$/', $behavior);
        $this->assertTrue($handler->hasRecordThatMatches($regex, LogLevel::DEBUG));
    }

    public function testInteractiveSetTimeoutOnTheFly(): void
    {
        $timeoutValue = 2;
        $behavior     = SSHConfig::SIGNAL_TERMINATE;
        $command      = 'tail -f /etc/hostname';

        $commander = $this->getSSHCommander($this->sshOptions);
        $this->assertTrue($commander->getConnection()->isAuthenticated());

        // set timeout on the fly
        $commander->timeout($timeoutValue, $behavior);

        $result = $commander->run($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertFalse($result->isTimelimit());

        // check that the behavior was executed
        /** @var TestHandler $handler */
        $handler = $commander->getLogger()->popHandler();
        $regex   = sprintf('/^WRITE: %s$/', $behavior);
        $this->assertTrue($handler->hasRecordThatMatches($regex, LogLevel::DEBUG));
    }

    public function testInteractiveSetTimelimitFromGlobalConfig(): void
    {
        $timelimitValue = 2;
        $behavior       = SSHConfig::SIGNAL_TERMINATE;
        // for the timelimit cases, we need a command that will never time out
        $command = 'ping 127.0.0.1';

        $config = array_merge(
            $this->sshOptions,
            [
                'timelimit'          => $timelimitValue,
                'timelimit_behavior' => $behavior,
            ]
        );

        $commander = $this->getSSHCommander($config);
        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->run($command);

        $this->assertEquals($timelimitValue, (int)$result->getCommandElapsedTime());
        $this->assertFalse($result->isTimeout());
        $this->assertTrue($result->isTimelimit());

        // check that the behavior was executed
        /** @var TestHandler $handler */
        $handler = $commander->getLogger()->popHandler();
        $regex   = sprintf('/^WRITE: %s$/', $behavior);
        $this->assertTrue($handler->hasRecordThatMatches($regex, LogLevel::DEBUG));
    }

    public function testInteractiveSetTimelimitFromCommandConfig(): void
    {
        $timelimitValue = 2;
        $behavior       = SSHConfig::SIGNAL_TERMINATE;
        // for the timelimit cases, we need a command that will never time out
        $command = 'ping 127.0.0.1';

        $config = [
            'timelimit'          => $timelimitValue,
            'timelimit_behavior' => $behavior,
        ];

        $commander = $this->getSSHCommander($this->sshOptions);
        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->run($command, $config);

        $this->assertEquals($timelimitValue, (int)$result->getCommandElapsedTime());
        $this->assertFalse($result->isTimeout());
        $this->assertTrue($result->isTimelimit());

        // check that the behavior was executed
        /** @var TestHandler $handler */
        $handler = $commander->getLogger()->popHandler();
        $regex   = sprintf('/^WRITE: %s$/', $behavior);
        $this->assertTrue($handler->hasRecordThatMatches($regex, LogLevel::DEBUG));
    }

    public function testInteractiveSetTimelimitOnTheFly(): void
    {
        $timelimitValue    = 2;
        $behavior = SSHConfig::SIGNAL_TERMINATE;
        // for the timelimit cases, we need a command that will never time out
        $command = 'ping 127.0.0.1';

        $commander = $this->getSSHCommander($this->sshOptions);
        $this->assertTrue($commander->getConnection()->isAuthenticated());

        // set time limit on the fly
        $commander->timelimit($timelimitValue, $behavior);

        $result = $commander->run($command);

        $this->assertEquals($timelimitValue, (int)$result->getCommandElapsedTime());
        $this->assertFalse($result->isTimeout());
        $this->assertTrue($result->isTimelimit());

        // check that the behavior was executed
        /** @var TestHandler $handler */
        $handler = $commander->getLogger()->popHandler();
        $regex   = sprintf('/^WRITE: %s$/', $behavior);
        $this->assertTrue($handler->hasRecordThatMatches($regex, LogLevel::DEBUG));
    }
}

<?php /** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpUnhandledExceptionInspection */
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
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;
        $condition    = SSHConfig::TIMEOUT_CONDITION_NOOUT;

        $command      = 'tail -f /etc/hostname';

        $config = array_merge(
            $this->sshOptions,
            [
                'timeout'          => $timeoutValue,
                'timeout_conditon' => $condition,
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
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;
        $condition    = SSHConfig::TIMEOUT_CONDITION_NOOUT;
        $command      = 'tail -f /etc/hostname';

        $config = [
            'timeout'          => $timeoutValue,
            'timeout_conditon' => $condition,
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
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;
        $condition    = SSHConfig::TIMEOUT_CONDITION_NOOUT;

        $command      = 'tail -f /etc/hostname';

        $commander = $this->getSSHCommander($this->sshOptions);
        $this->assertTrue($commander->getConnection()->isAuthenticated());

        // set timeout on the fly
        $commander->timeout($timeoutValue, $condition, $behavior);

        $result = $commander->runIsolated($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertFalse($result->isTimelimit());
    }

    public function testIsolatedSetTimelimitFromGlobalConfig(): void
    {
        $timeoutValue = 2;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;
        $condition    = SSHConfig::TIMEOUT_CONDITION_RUNTIME;

        $command      = 'ping 127.0.0.1';

        $config = array_merge(
            $this->sshOptions,
            [
                'timeout'          => $timeoutValue,
                'timeout_conditon' => $condition,
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

    public function testIsolatedSetTimelimitFromCommandConfig(): void
    {
        $timeoutValue = 2;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;
        $condition    = SSHConfig::TIMEOUT_CONDITION_RUNTIME;
        $command      = 'tail -f /etc/hostname';

        $config = [
            'timeout'          => $timeoutValue,
            'timeout_conditon' => $condition,
            'timeout_behavior' => $behavior,
        ];

        $commander = $this->getSSHCommander($this->sshOptions);

        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->runIsolated($command, $config);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertFalse($result->isTimelimit());
    }

    public function testIsolatedSetTimelimitOnTheFly(): void
    {
        $timeoutValue = 2;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;
        $condition    = SSHConfig::TIMEOUT_CONDITION_RUNTIME;

        $command      = 'tail -f /etc/hostname';

        $commander = $this->getSSHCommander($this->sshOptions);
        $this->assertTrue($commander->getConnection()->isAuthenticated());

        // set timeout on the fly
        $commander->timeout($timeoutValue, $condition, $behavior);

        $result = $commander->runIsolated($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertFalse($result->isTimelimit());
    }

    public function testIsolatedSetTimelimitSleep(): void
    {
        $this->enableDebugLog();

        $timeoutValue = 2;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;
        $condition    = SSHConfig::TIMEOUT_CONDITION_RUNTIME;

        $command      = 'sleep 5';

        $commander = $this->getSSHCommander($this->sshOptions);
        $this->assertTrue($commander->getConnection()->isAuthenticated());

        // set timeout on the fly
        $commander->timeout($timeoutValue, $condition, $behavior);

        $result = $commander->runIsolated($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertFalse($result->isTimelimit());

        // check that the behavior was executed
        /** @var TestHandler $handler */
        $handler = $commander->getLogger()->popHandler();
        $regex   = sprintf('/^WRITE: %s$/', $behavior);
        $this->assertTrue($handler->hasRecordThatMatches($regex, LogLevel::DEBUG));
    }

    public function testInteractiveSetTimeoutFromGlobalConfig(): void
    {
        $this->enableDebugLog();

        $timeoutValue = 2;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;
        $condition    = SSHConfig::TIMEOUT_CONDITION_NOOUT;

        $command      = 'tail -f /etc/hostname';

        $config = array_merge(
            $this->sshOptions,
            [
                'timeout'          => $timeoutValue,
                'timeout_conditon' => $condition,
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
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;
        $condition    = SSHConfig::TIMEOUT_CONDITION_NOOUT;

        $command      = 'tail -f /etc/hostname';

        $config = [
            'timeout'          => $timeoutValue,
            'timeout_conditon' => $condition,
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
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;
        $condition    = SSHConfig::TIMEOUT_CONDITION_NOOUT;

        $command      = 'tail -f /etc/hostname';

        $commander = $this->getSSHCommander($this->sshOptions);
        $this->assertTrue($commander->getConnection()->isAuthenticated());

        // set timeout on the fly
        $commander->timeout($timeoutValue, $condition, $behavior);

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
        $condition      = SSHConfig::TIMEOUT_CONDITION_RUNTIME;
        $behavior       = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;
        // for the timelimit cases, we need a command that will never trigger
        // the 'noout' condition, i.e. one that will constantly produce output
        $command = 'ping 127.0.0.1';

        $config = array_merge(
            $this->sshOptions,
            [
                'timeout'          => $timelimitValue,
                'timeout_conditon' => $condition,
                'timeout_behavior' => $behavior,
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
        $condition      = SSHConfig::TIMEOUT_CONDITION_RUNTIME;
        $behavior       = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;
        // for the timelimit cases, we need a command that will never trigger
        // the 'noout' condition, i.e. one that will constantly produce output
        $command = 'ping 127.0.0.1';

        $config = [
            'timeout'          => $timelimitValue,
            'timeout_conditon' => $condition,
            'timeout_behavior' => $behavior,
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
        $timelimitValue = 2;
        $condition      = SSHConfig::TIMEOUT_CONDITION_RUNTIME;
        $behavior       = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;
        // for the timelimit cases, we need a command that will never trigger
        // the 'noout' condition, i.e. one that will constantly produce output
        $command = 'ping 127.0.0.1';

        $commander = $this->getSSHCommander($this->sshOptions);
        $this->assertTrue($commander->getConnection()->isAuthenticated());

        // set time limit on the fly
        $commander->timeout($timelimitValue, $condition, $behavior);

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

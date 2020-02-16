<?php /** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection DuplicatedCode */

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Interfaces\SSHCommanderInterface;
use Neskodi\SSHCommander\Tests\IntegrationTestCase;
use Neskodi\SSHCommander\SSHCommand;
use Neskodi\SSHCommander\SSHConfig;
use Monolog\Handler\TestHandler;
use Psr\Log\LogLevel;

class TimeoutTest extends IntegrationTestCase
{
    public function testInteractiveTimelimitPing(): void
    {
        $timeoutValue = 2;
        $condition    = SSHConfig::TIMEOUT_CONDITION_RUNNING_TIMELIMIT;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;

        $command = new SSHCommand('ping 127.0.0.1');

        $config = array_merge(
            $this->sshOptions,
            [
                'timeout'           => $timeoutValue,
                'timeout_condition' => $condition,
                'timeout_behavior'  => $behavior,
            ]
        );

        $commander = $this->getSSHCommander($config);

        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->run($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertTrue($result->isTimelimit());
        $this->assertBehaviorWasExecuted($commander, $behavior);
        $this->assertCanRunSubsequentCommand($commander);
    }

    public function testInteractiveTimelimitTail(): void
    {
        $timeoutValue = 2;
        $condition    = SSHConfig::TIMEOUT_CONDITION_RUNNING_TIMELIMIT;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;

        $command = new SSHCommand('tail -f /etc/hostname');

        $config = array_merge(
            $this->sshOptions,
            [
                'timeout'           => $timeoutValue,
                'timeout_condition' => $condition,
                'timeout_behavior'  => $behavior,
            ]
        );

        $commander = $this->getSSHCommander($config);

        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->run($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertTrue($result->isTimelimit());
        $this->assertBehaviorWasExecuted($commander, $behavior);
        $this->assertCanRunSubsequentCommand($commander);
    }

    public function testInteractiveTimelimitSleep(): void
    {
        $timeoutValue = 2;
        $condition    = SSHConfig::TIMEOUT_CONDITION_RUNNING_TIMELIMIT;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;

        $command = new SSHCommand('sleep 5');

        $config = array_merge(
            $this->sshOptions,
            [
                'timeout'           => $timeoutValue,
                'timeout_condition' => $condition,
                'timeout_behavior'  => $behavior,
            ]
        );

        $commander = $this->getSSHCommander($config);

        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->run($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertTrue($result->isTimelimit());
        $this->assertBehaviorWasExecuted($commander, $behavior);
        $this->assertCanRunSubsequentCommand($commander);
    }

    public function testIsolatedTimelimitPing(): void
    {
        $timeoutValue = 2;
        $condition    = SSHConfig::TIMEOUT_CONDITION_RUNNING_TIMELIMIT;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;

        $command = new SSHCommand('ping 127.0.0.1');

        $config = array_merge(
            $this->sshOptions,
            [
                'timeout'           => $timeoutValue,
                'timeout_condition' => $condition,
                'timeout_behavior'  => $behavior,
            ]
        );

        $commander = $this->getSSHCommander($config);

        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->runIsolated($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertTrue($result->isTimelimit());
        $this->assertBehaviorWasExecuted($commander, $behavior);
        $this->assertCanRunSubsequentCommand($commander);
    }

    public function testIsolatedTimelimitTail(): void
    {
        $timeoutValue = 2;
        $condition    = SSHConfig::TIMEOUT_CONDITION_RUNNING_TIMELIMIT;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;

        $command = new SSHCommand('tail -f /etc/hostname');

        $config = array_merge(
            $this->sshOptions,
            [
                'timeout'           => $timeoutValue,
                'timeout_condition' => $condition,
                'timeout_behavior'  => $behavior,
            ]
        );

        $commander = $this->getSSHCommander($config);

        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->runIsolated($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertTrue($result->isTimelimit());
        $this->assertBehaviorWasExecuted($commander, $behavior);
        $this->assertCanRunSubsequentCommand($commander);
    }

    public function testIsolatedTimelimitSleep(): void
    {
        $timeoutValue = 2;
        $condition    = SSHConfig::TIMEOUT_CONDITION_RUNNING_TIMELIMIT;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;

        $command = new SSHCommand('sleep 5');

        $config = array_merge(
            $this->sshOptions,
            [
                'timeout'           => $timeoutValue,
                'timeout_condition' => $condition,
                'timeout_behavior'  => $behavior,
            ]
        );

        $commander = $this->getSSHCommander($config);

        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->runIsolated($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertTrue($result->isTimelimit());
        $this->assertBehaviorWasExecuted($commander, $behavior);
        $this->assertCanRunSubsequentCommand($commander);
    }



    /**
     * Check that Commander has executed the required behavior.
     *
     * @param SSHCommanderInterface $commander
     * @param string                $behavior
     */
    protected function assertBehaviorWasExecuted(
        SSHCommanderInterface $commander,
        string $behavior
    ): void {
        /** @var TestHandler $handler */
        $handler = $commander->getLogger()->popHandler();
        $regex   = sprintf('/^WRITE: %s$/', $behavior);
        $this->assertTrue($handler->hasRecordThatMatches($regex, LogLevel::DEBUG));
    }

    /**
     * Check that other commands can run after timeout was handled
     *
     * @param SSHCommanderInterface $commander
     */
    protected function assertCanRunSubsequentCommand(
        SSHCommanderInterface $commander
    ): void {
        $this->assertEquals(
            $commander->getConfig('user'),
            (string)$commander->run('whoami')
        );
    }
}

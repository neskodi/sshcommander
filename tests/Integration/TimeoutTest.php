<?php /** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection DuplicatedCode */

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommanderInterface;
use Neskodi\SSHCommander\Tests\IntegrationTestCase;
use Neskodi\SSHCommander\SSHCommand;
use Neskodi\SSHCommander\SSHConfig;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class TimeoutTest extends IntegrationTestCase
{
    public function testIsolatedSetTimeoutFromGlobalConfig(): void
    {
        $this->enableDebugLog();

        $timeoutValue = 3;
        $condition    = SSHConfig::TIMEOUT_CONDITION_NOOUT;
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

        /** @var LoggerInterface $logger */
        $logger = $commander->getLogger();
        $command->addReadCycleHook(function (SSHConnectionInterface $connection, $out) use ($logger) {
            $logger->debug("Current iteration output: \t" . $out);
            $logger->debug("Full output: \t\t\t\t" . $connection->getOutputProcessor()->getAsString());
            $logger->debug("Time since command start: \t" . $connection->timeSinceCommandStart());
            $logger->debug("Time since last response: \t" . $connection->timeSinceLastResponse());
            $logger->debug("-----");
        });

        $result = $commander->runIsolated($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertFalse($result->isTimelimit());
        // $this->assertBehaviorWasExecuted($commander, $behavior);
    }

    public function testIsolatedSetTimeoutFromCommandConfig(): void
    {
        $timeoutValue = 2;
        $condition    = SSHConfig::TIMEOUT_CONDITION_NOOUT;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;

        $command = 'tail -f /etc/hostname';

        $config = [
            'timeout'           => $timeoutValue,
            'timeout_condition' => $condition,
            'timeout_behavior'  => $behavior,
        ];

        $commander = $this->getSSHCommander($this->sshOptions);

        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->runIsolated($command, $config);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertFalse($result->isTimelimit());
        $this->assertBehaviorWasExecuted($commander, $behavior);
    }

    public function testIsolatedSetTimeoutOnTheFly(): void
    {
        $timeoutValue = 2;
        $condition    = SSHConfig::TIMEOUT_CONDITION_NOOUT;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;

        $command = 'tail -f /etc/hostname';

        $commander = $this->getSSHCommander($this->sshOptions);
        $this->assertTrue($commander->getConnection()->isAuthenticated());

        // set timeout on the fly
        $commander->timeout($timeoutValue, $condition, $behavior);

        $result = $commander->runIsolated($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertFalse($result->isTimelimit());
        $this->assertBehaviorWasExecuted($commander, $behavior);
    }

    public function testIsolatedSetTimelimitFromGlobalConfig(): void
    {
        $timeoutValue = 2;
        $condition    = SSHConfig::TIMEOUT_CONDITION_RUNTIME;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;

        $command = 'ping 127.0.0.1';

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
    }

    public function testIsolatedSetTimelimitFromCommandConfig(): void
    {
        $timeoutValue = 2;
        $condition    = SSHConfig::TIMEOUT_CONDITION_RUNTIME;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;

        $command = 'ping 127.0.0.1';

        $config = [
            'timeout'           => $timeoutValue,
            'timeout_condition' => $condition,
            'timeout_behavior'  => $behavior,
        ];

        $commander = $this->getSSHCommander($this->sshOptions);

        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->runIsolated($command, $config);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertTrue($result->isTimelimit());
        $this->assertBehaviorWasExecuted($commander, $behavior);
    }

    public function testIsolatedSetTimelimitOnTheFly(): void
    {
        $timeoutValue = 2;
        $condition    = SSHConfig::TIMEOUT_CONDITION_RUNTIME;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;

        $command = 'ping 127.0.0.1';

        $commander = $this->getSSHCommander($this->sshOptions);
        $this->assertTrue($commander->getConnection()->isAuthenticated());

        // set timeout on the fly
        $commander->timeout($timeoutValue, $condition, $behavior);

        $result = $commander->runIsolated($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertTrue($result->isTimelimit());
        $this->assertBehaviorWasExecuted($commander, $behavior);
    }

    public function testIsolatedSetTimelimitSleep(): void
    {
        $timeoutValue = 2;
        $condition    = SSHConfig::TIMEOUT_CONDITION_RUNTIME;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;

        $command = 'sleep 5';

        $commander = $this->getSSHCommander($this->sshOptions);
        $this->assertTrue($commander->getConnection()->isAuthenticated());

        // set timeout on the fly
        $commander->timeout($timeoutValue, $condition, $behavior);

        $result = $commander->runIsolated($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertTrue($result->isTimelimit());
        $this->assertBehaviorWasExecuted($commander, $behavior);
    }

    public function testInteractiveSetTimeoutFromGlobalConfig(): void
    {
        $timeoutValue = 2;
        $condition    = SSHConfig::TIMEOUT_CONDITION_NOOUT;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;

        $command = 'tail -f /etc/hostname';

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
        $this->assertFalse($result->isTimelimit());
        $this->assertBehaviorWasExecuted($commander, $behavior);
    }

    public function testInteractiveSetTimeoutFromCommandConfig(): void
    {
        $timeoutValue = 2;
        $condition    = SSHConfig::TIMEOUT_CONDITION_NOOUT;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;

        $command = 'tail -f /etc/hostname';

        $config = [
            'timeout'           => $timeoutValue,
            'timeout_condition' => $condition,
            'timeout_behavior'  => $behavior,
        ];

        $commander = $this->getSSHCommander($this->sshOptions);

        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->run($command, $config);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertFalse($result->isTimelimit());
        $this->assertBehaviorWasExecuted($commander, $behavior);
    }

    public function testInteractiveSetTimeoutOnTheFly(): void
    {
        $timeoutValue = 2;
        $condition    = SSHConfig::TIMEOUT_CONDITION_NOOUT;
        $behavior     = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;

        $command = 'tail -f /etc/hostname';

        $commander = $this->getSSHCommander($this->sshOptions);

        $this->assertTrue($commander->getConnection()->isAuthenticated());

        // set timeout on the fly
        $commander->timeout($timeoutValue, $condition, $behavior);

        $result = $commander->run($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertFalse($result->isTimelimit());
        $this->assertBehaviorWasExecuted($commander, $behavior);
    }

    public function testInteractiveSetTimelimitFromGlobalConfig(): void
    {
        $timelimitValue = 2;
        $condition      = SSHConfig::TIMEOUT_CONDITION_RUNTIME;
        $behavior       = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;

        $command = 'ping 127.0.0.1';

        $config = array_merge(
            $this->sshOptions,
            [
                'timeout'           => $timelimitValue,
                'timeout_condition' => $condition,
                'timeout_behavior'  => $behavior,
            ]
        );

        $commander = $this->getSSHCommander($config);

        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->run($command);

        $this->assertEquals($timelimitValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertTrue($result->isTimelimit());
        $this->assertBehaviorWasExecuted($commander, $behavior);
    }

    public function testInteractiveSetTimelimitFromCommandConfig(): void
    {
        $timelimitValue = 2;
        $condition      = SSHConfig::TIMEOUT_CONDITION_RUNTIME;
        $behavior       = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;

        $command = 'ping 127.0.0.1';

        $config = [
            'timeout'           => $timelimitValue,
            'timeout_condition' => $condition,
            'timeout_behavior'  => $behavior,
        ];

        $commander = $this->getSSHCommander($this->sshOptions);

        $this->assertTrue($commander->getConnection()->isAuthenticated());

        $result = $commander->run($command, $config);

        $this->assertEquals($timelimitValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertTrue($result->isTimelimit());
        $this->assertBehaviorWasExecuted($commander, $behavior);
    }

    public function testInteractiveSetTimelimitOnTheFly(): void
    {
        $timelimitValue = 2;
        $condition      = SSHConfig::TIMEOUT_CONDITION_RUNTIME;
        $behavior       = SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE;

        $command = 'ping 127.0.0.1';

        $commander = $this->getSSHCommander($this->sshOptions);

        $this->assertTrue($commander->getConnection()->isAuthenticated());

        // set time limit on the fly
        $commander->timeout($timelimitValue, $condition, $behavior);

        $result = $commander->run($command);

        $this->assertEquals($timelimitValue, (int)$result->getCommandElapsedTime());
        $this->assertTrue($result->isTimeout());
        $this->assertTrue($result->isTimelimit());
        $this->assertBehaviorWasExecuted($commander, $behavior);
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
}

<?php /** @noinspection PhpRedundantCatchClauseInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\integration;

use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Interfaces\CommandInterface;
use Neskodi\SSHCommander\Factories\LoggerFactory;
use Monolog\Processor\PsrLogMessageProcessor;
use Neskodi\SSHCommander\Tests\TestCase;
use Neskodi\SSHCommander\SSHCommander;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Monolog\Logger;

class LoggingTest extends TestCase
{
    const TEST_OUTPUT_STRING = 'quick brown fox jumps over the lazy dog';

    const COMMAND_COMPLETION_REGEX = '/Command completed in \d+(\.\d+)? seconds/';

    /**
     * @var SSHCommander
     */
    protected $commander;

    /**
     * @var CommandInterface
     */
    protected $successfulCommand;

    /**
     * @var CommandInterface
     */
    protected $unsuccessfulCommand;

    /**
     * @var array
     */
    protected $logRecords = [];

    protected function setUp(): void
    {
        $this->buildSshOptions();

        if (empty($this->sshOptions)) {
            // we can't test anything without a working connection
            $this->markTestSkipped(
                'SSHCommander needs a working SSH connection ' .
                'to run integration tests. Please set the connection ' .
                'information in phpunit.xml.');
        }

        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            // we can't test anything without a working connection
            $this->markTestSkipped($e->getMessage());
        }
    }

    protected function getSuccessfulCommand(): string
    {
        if (!$this->successfulCommand) {
            $this->successfulCommand = sprintf(
                'echo "%s"',
                self::TEST_OUTPUT_STRING
            );
        }

        return $this->successfulCommand;
    }

    protected function getUnsuccessfulCommand(): string
    {
        if (!$this->unsuccessfulCommand) {
            $this->unsuccessfulCommand = 'cd /n0/such/d!r3ct0ry';
        }

        return $this->unsuccessfulCommand;
    }

    protected function getCommander(): SSHCommander
    {
        if (!$this->commander) {
            $this->commander = new SSHCommander($this->sshOptions);
        }

        return $this->commander;
    }

    protected function getLogger(string $level): LoggerInterface
    {
        $logger = new Logger('test-ssh-commander-log');
        $logger->pushProcessor(new PsrLogMessageProcessor);

        $handler = new TestHandler($level);
        $handler->setFormatter(
            LoggerFactory::getStreamLineFormatter(
                $this->getCommander()->getConfig()
            )
        );
        $logger->pushHandler($handler);

        return $logger;
    }

    protected function runCommand(string $level, bool $successful): void
    {
        $command = $successful
            ? $this->getSuccessfulCommand()
            : $this->getUnsuccessfulCommand();

        // get a fresh logger instance for each command
        $this->getCommander()->setLogger($this->getLogger($level));

        // run the command to collect log output
        $this->getCommander()->run($command);
    }

    protected function getCommandLogRecords(
        string $level,
        string $outcome
    ): TestHandler
    {
        $key = "$outcome-$level";

        if (!isset($this->logRecords[$key])) {
            $this->runCommand($level, ('success' === $outcome));

            /** @var TestHandler $handler */
            $handler = $this->getCommander()->getLogger()->popHandler();

            $this->logRecords[$key] = $handler;
        }

        return $this->logRecords[$key];
    }

    /**
     * @param string $prefix
     *
     * @return string
     */
    protected function getTestOutputStringRegex(
        string $prefix = 'Running command:'
    ): string {
        $regex = '%s.+?%s';
        $regex = sprintf($regex, $prefix, self::TEST_OUTPUT_STRING);
        $regex = "/$regex/si";

        return $regex;
    }

    protected function getAuthFailedCommander(string $level)
    {
        $options = array_merge($this->sshOptions, [
            'user' => '****',
        ]);

        $commander = new SSHCommander($options);
        $commander->setLogger($this->getLogger($level));

        return $commander;
    }

    public function testCommandOutputIsLoggedOnDebugLevel(): void
    {
        $level = LogLevel::DEBUG;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertEquals(Logger::DEBUG, $handler->getLevel());

        $this->assertTrue(
            $handler->hasRecordThatContains('Command returned:', $level)
        );
    }

    public function testCommandOutputIsNotLoggedAboveDebugLevel(): void
    {
        $level = LogLevel::INFO;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertEquals(Logger::INFO, $handler->getLevel());

        $this->assertFalse(
            $handler->hasRecordThatContains('Command returned:', $level)
        );
    }

    public function testCommandSuccessIsLoggedOnDebugLevel(): void
    {
        $level = LogLevel::DEBUG;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertEquals(Logger::DEBUG, $handler->getLevel());

        $this->assertTrue(
            $handler->hasRecordThatContains(
                'Command returned exit status: ok (code 0)',
                $level
            )
        );
    }

    public function testCommandSuccessIsNotLoggedAboveDebugLevel(): void
    {
        $level = LogLevel::INFO;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertEquals(Logger::INFO, $handler->getLevel());

        $this->assertFalse(
            $handler->hasRecordThatContains(
                'Command returned exit status: ok (code 0)',
                $level
            )
        );
    }

    public function testConnectionStatusIsLoggedOnInfoLevel(): void
    {
        $level = LogLevel::INFO;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertEquals(Logger::INFO, $handler->getLevel());

        $this->assertTrue(
            $handler->hasRecordThatContains('Authenticated', $level)
        );
    }

    public function testConnectionStatusIsNotLoggedAboveInfoLevel(): void
    {
        $level = LogLevel::NOTICE;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertEquals(Logger::NOTICE, $handler->getLevel());

        $this->assertFalse(
            $handler->hasRecordThatContains('Authenticated', $level)
        );
    }

    public function testCommandIsLoggedOnInfoLevel(): void
    {
        $level = LogLevel::INFO;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertEquals(Logger::INFO, $handler->getLevel());

        $regex = $this->getTestOutputStringRegex('Running command:');

        $this->assertTrue(
            $handler->hasRecordThatMatches($regex, $level)
        );
    }

    public function testCommandIsNotLoggedAboveInfoLevel(): void
    {
        $level = LogLevel::NOTICE;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertEquals(Logger::NOTICE, $handler->getLevel());

        $regex = $this->getTestOutputStringRegex('Running command:');

        $this->assertFalse(
            $handler->hasRecordThatMatches($regex, $level)
        );
    }

    public function testCommandCompletionIsLoggedOnInfoLevel(): void
    {
        $level = LogLevel::INFO;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertEquals(Logger::INFO, $handler->getLevel());

        $this->assertTrue(
            $handler->hasRecordThatMatches(static::COMMAND_COMPLETION_REGEX, $level)
        );
    }

    public function testCommandCompletionIsNotLoggedAboveInfoLevel(): void
    {
        $level = LogLevel::NOTICE;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertEquals(Logger::NOTICE, $handler->getLevel());

        $this->assertFalse(
            $handler->hasRecordThatMatches(static::COMMAND_COMPLETION_REGEX, $level)
        );
    }

    public function testCommandErrorLoggedOnNoticeLevel(): void
    {
        $level = LogLevel::NOTICE;

        $handler = $this->getCommandLogRecords($level, 'failure');

        $this->assertEquals(Logger::NOTICE, $handler->getLevel());

        $this->assertTrue(
            $handler->hasRecordThatContains('Command returned error status:', $level)
        );
    }

    public function testCommandErrorIsNotLoggedAboveNoticeLevel(): void
    {
        $level = LogLevel::ERROR;

        $handler = $this->getCommandLogRecords($level, 'failure');

        $this->assertEquals(Logger::ERROR, $handler->getLevel());

        $this->assertFalse(
            $handler->hasRecordThatContains('Command returned error status:', $level)
        );
    }

    public function testLoginErrorLoggedOnErrorLevel(): void
    {
        $level = LogLevel::ERROR;

        $commander = $this->getAuthFailedCommander($level);

        try {
            $command = $this->getSuccessfulCommand();
            $commander->run($command);
        } catch (AuthenticationException $e) {
            /** @var TestHandler $handler */
            $handler = $commander->getLogger()->popHandler();
            $this->assertTrue($handler->hasRecordThatContains(
                'Failed to authenticate to remote host',
                $level
            ));
        }
    }

    public function testLoginErrorIsNotLoggedAboveErrorLevel(): void
    {
        $level = LogLevel::CRITICAL;

        $commander = $this->getAuthFailedCommander($level);

        try {
            $command = $this->getSuccessfulCommand();
            $commander->run($command);
        } catch (AuthenticationException $e) {
            /** @var TestHandler $handler */
            $handler = $commander->getLogger()->popHandler();
            $this->assertSame(0, count($handler->getRecords()));
        }
    }

    public function testSeparateStdErrIsLoggedOnDebugLevel()
    {

    }
}

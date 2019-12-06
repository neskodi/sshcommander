<?php /** @noinspection PhpRedundantCatchClauseInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\integration;

use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Tests\TestCase;
use Neskodi\SSHCommander\SSHCommander;
use Monolog\Handler\TestHandler;
use Psr\Log\LogLevel;
use RuntimeException;
use Monolog\Logger;
use Exception;

class LoggingTest extends TestCase
{
    const TEST_OUTPUT_STRING       = 'quick brown fox jumps over the lazy dog';

    const LOGIN_SUCCESS_MARKER     = 'Authenticated';
    const LOGIN_FAILED_MARKER      = 'Failed to authenticate to remote host';
    const COMMAND_RUNNING_MARKER   = 'Running command:';
    const COMMAND_COMPLETION_REGEX = '/Command completed in \d+(\.\d+)? seconds/';
    const COMMAND_OUTPUT_MARKER    = 'Command returned:';
    const COMMAND_STDERR_MARKER    = 'Command STDERR:';
    const COMMAND_SUCCESS_MARKER   = 'Command returned exit status: ok (code 0)';
    const COMMAND_ERROR_MARKER     = 'Command returned error status:';

    /**
     * @var SSHCommander
     */
    protected $commander;

    /**
     * @var SSHCommandInterface
     */
    protected $successfulCommand;

    /**
     * @var SSHCommandInterface
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
                'information in phpunit.xml.'
            );
        }

        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            // we can't test anything without a working connection
            $this->markTestSkipped($e->getMessage());
        }
    }

    /**
     * Get a command that is guaranteed to succeed on any system.
     *
     * @return string
     */
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

    /**
     * Get a command that is guaranteed to produce an error output.
     *
     * @return string
     */
    protected function getUnsuccessfulCommand(): string
    {
        if (!$this->unsuccessfulCommand) {
            $this->unsuccessfulCommand = 'cd /no/such/directory!';
        }

        return $this->unsuccessfulCommand;
    }

    /**
     * Get the singleton instance of SSHCommander (will be used for most
     * commands in this test).
     *
     * @return SSHCommander
     * @throws Exception
     */
    protected function getCommander(): SSHCommander
    {
        if (!$this->commander) {
            $this->commander = new SSHCommander($this->sshOptions);
        }

        return $this->commander;
    }

    /**
     * Run the successful / unsuccessful command, using a logger of the
     * specified level.
     *
     * @param string $level      the level of logger to use
     * @param bool   $successful whether to target success or error output
     * @param array  $options    any additional options to run with
     *
     * @throws Exception
     */
    protected function runCommand(
        string $level,
        bool $successful,
        array $options = []
    ): void {
        $command = $successful
            ? $this->getSuccessfulCommand()
            : $this->getUnsuccessfulCommand();

        // get a fresh logger instance for each command
        $this->getCommander()->setLogger($this->getTestLogger($level));

        // run the command to collect log output
        $this->getCommander()->run($command, $options);
    }

    /**
     * Get log records from the run of command with specified outcome and
     * logging level. The same outcome/level combo may be used in multiple tests
     * but the command is run only once and then the logging output from this
     * combo is cached and reused.
     *
     * @param string $level   the required logging level
     * @param string $outcome the required command outcome
     *
     * @return TestHandler
     * @throws Exception
     */
    protected function getCommandLogRecords(
        string $level,
        string $outcome
    ): TestHandler {
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
     * Build a regular expression to test the presence of test output string
     * in command log.
     *
     * @param string $prefix what the log string should begin with
     *
     * @return string
     */
    protected function getTestOutputStringRegex(
        string $prefix = self::COMMAND_RUNNING_MARKER
    ): string {
        $regex = '%s.+?%s';
        $regex = sprintf($regex, $prefix, self::TEST_OUTPUT_STRING);
        $regex = "/$regex/si";

        return $regex;
    }

    /**
     * Get a separate instance of the commander with failed authentication
     * to inspect its log.
     *
     * @param string $level the logging level to use.
     *
     * @return SSHCommander
     * @throws Exception
     */
    protected function getAuthFailedCommander(string $level)
    {
        $options = array_merge(
            $this->sshOptions,
            [
                'user' => '****',
            ]
        );

        $commander = new SSHCommander($options);
        $commander->setLogger($this->getTestLogger($level));

        return $commander;
    }

    public function testCommandOutputIsLoggedOnDebugLevel(): void
    {
        $level = LogLevel::DEBUG;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertEquals(Logger::DEBUG, $handler->getLevel());

        $this->assertTrue(
            $handler->hasRecordThatContains(self::COMMAND_OUTPUT_MARKER, $level)
        );
    }

    public function testCommandOutputIsNotLoggedAboveDebugLevel(): void
    {
        $level = LogLevel::INFO;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertEquals(Logger::INFO, $handler->getLevel());

        $this->assertFalse(
            $handler->hasRecordThatContains(self::COMMAND_OUTPUT_MARKER, $level)
        );
    }

    public function testCommandSuccessIsLoggedOnDebugLevel(): void
    {
        $level = LogLevel::DEBUG;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertEquals(Logger::DEBUG, $handler->getLevel());

        $this->assertTrue(
            $handler->hasRecordThatContains(
                self::COMMAND_SUCCESS_MARKER,
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
                self::COMMAND_SUCCESS_MARKER,
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
            $handler->hasRecordThatContains(
                self::LOGIN_SUCCESS_MARKER,
                $level
            )
        );
    }

    public function testConnectionStatusIsNotLoggedAboveInfoLevel(): void
    {
        $level = LogLevel::NOTICE;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertEquals(Logger::NOTICE, $handler->getLevel());

        $this->assertFalse(
            $handler->hasRecordThatContains(
                self::LOGIN_SUCCESS_MARKER,
                $level
            )
        );
    }

    public function testCommandIsLoggedOnInfoLevel(): void
    {
        $level = LogLevel::INFO;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertEquals(Logger::INFO, $handler->getLevel());

        $regex = $this->getTestOutputStringRegex(self::COMMAND_RUNNING_MARKER);

        $this->assertTrue(
            $handler->hasRecordThatMatches($regex, $level)
        );
    }

    public function testCommandIsNotLoggedAboveInfoLevel(): void
    {
        $level = LogLevel::NOTICE;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertEquals(Logger::NOTICE, $handler->getLevel());

        $regex = $this->getTestOutputStringRegex(self::COMMAND_RUNNING_MARKER);

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
            $handler->hasRecordThatMatches(
                static::COMMAND_COMPLETION_REGEX,
                $level
            )
        );
    }

    public function testCommandCompletionIsNotLoggedAboveInfoLevel(): void
    {
        $level = LogLevel::NOTICE;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertEquals(Logger::NOTICE, $handler->getLevel());

        $this->assertFalse(
            $handler->hasRecordThatMatches(
                static::COMMAND_COMPLETION_REGEX,
                $level
            )
        );
    }

    public function testCommandErrorLoggedOnNoticeLevel(): void
    {
        $level = LogLevel::NOTICE;

        $handler = $this->getCommandLogRecords($level, 'failure');

        $this->assertEquals(Logger::NOTICE, $handler->getLevel());

        $this->assertTrue(
            $handler->hasRecordThatContains(
                self::COMMAND_ERROR_MARKER,
                $level
            )
        );
    }

    public function testCommandErrorIsNotLoggedAboveNoticeLevel(): void
    {
        $level = LogLevel::ERROR;

        $handler = $this->getCommandLogRecords($level, 'failure');

        $this->assertEquals(Logger::ERROR, $handler->getLevel());

        $this->assertFalse(
            $handler->hasRecordThatContains(
                self::COMMAND_ERROR_MARKER,
                $level
            )
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
            $this->assertTrue(
                $handler->hasRecordThatContains(
                    self::LOGIN_FAILED_MARKER,
                    $level
                )
            );
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
            $this->assertFalse(
                $handler->hasRecordThatContains(
                    self::LOGIN_FAILED_MARKER,
                    $level
                )
            );
        }
    }

    public function testSeparateStdErrIsLoggedOnDebugLevel(): void
    {
        $level = LogLevel::DEBUG;

        $this->runCommand($level, false, ['separate_stderr' => true]);

        /** @var TestHandler $handler */
        $handler = $this->getCommander()->getLogger()->popHandler();

        $this->assertTrue(
            $handler->hasRecordThatContains(
                self::COMMAND_STDERR_MARKER,
                $level
            )
        );
    }
}

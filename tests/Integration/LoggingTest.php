<?php /** @noinspection PhpRedundantCatchClauseInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Interfaces\SSHCommanderInterface;
use Neskodi\SSHCommander\Exceptions\CommandRunException;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Tests\IntegrationTestCase;
use Neskodi\SSHCommander\SSHCommander;
use Monolog\Handler\TestHandler;
use Psr\Log\LogLevel;
use Monolog\Logger;
use Exception;

class LoggingTest extends IntegrationTestCase
{
    const EXPECTED_OUTCOME_SUCCESS = 'success';
    const EXPECTED_OUTCOME_FAILURE = 'failure';

    const TEST_OUTPUT_STRING       = 'quick brown fox jumps over the lazy dog';

    const LOGIN_SUCCESS_MARKER     = 'Authenticated';
    const LOGIN_FAILED_MARKER      = 'Failed to authenticate to remote host';
    const COMMAND_RUNNING_MARKER   = 'Running command:';
    const COMMAND_COMPLETION_REGEX = '/Command completed in \d+(\.\d+)? seconds/';
    const COMMAND_OUTPUT_MARKER    = 'Command returned:';
    const COMMAND_STDERR_MARKER    = 'Command STDERR:';
    const COMMAND_SUCCESS_MARKER   = 'Command returned exit status: ok (code 0)';
    const COMMAND_ERROR_MARKER     = 'Command returned error code:';

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
     * @return SSHCommanderInterface
     * @throws Exception
     */
    protected function getCommander(): SSHCommanderInterface
    {
        if (!$this->commander) {
            $this->commander = new SSHCommander($this->sshOptions);
        }

        return $this->commander;
    }

    /**
     * Run the successful / unsuccessful command, using a new logger instance of
     * the specified level.
     *
     * @param string $level      the level of logger to use
     * @param bool   $successful whether to target success or error output
     * @param array  $options    any additional options to run with
     * @param bool   $isolated   whether to run the command in the isolated mode
     *
     * @throws Exception
     */
    protected function runCommandWithSeparateLogger(
        string $level,
        bool $successful,
        array $options = [],
        bool $isolated = false
    ): void {
        $command = $successful
            ? $this->getSuccessfulCommand()
            : $this->getUnsuccessfulCommand();

        // get a fresh logger instance for each command
        $this->getCommander()->setLogger($this->getTestLogger($level));

        // run the command to collect log output
        $method = $isolated ? 'runIsolated' : 'run';
        $this->getCommander()->$method($command, $options);
    }

    /**
     * Get log records from the run of command with specified outcome and
     * logging level.
     *
     * @param string $level    the required logging level
     * @param string $outcome  the required command outcome
     * @param bool   $isolated whether to run the command in the isolated mode
     * @param array  $options  options passed to the command
     *
     * @return TestHandler
     * @throws Exception
     */
    protected function runCommandAndGetLogRecords(
        string $level,
        string $outcome,
        bool $isolated = false,
        array $options = []
    ): TestHandler {
        try {
            $this->runCommandWithSeparateLogger(
                $level,
                (self::EXPECTED_OUTCOME_SUCCESS === $outcome),
                $options,
                $isolated
            );
        } catch (CommandRunException $e) {
            // disregard the exception
            // let's test that error records are present in logs even after
            // the exception has been thrown
        }

        /** @var TestHandler $handler */
        $handler = $this->getCommander()->getLogger()->popHandler();

        return $handler;
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
     * @return SSHCommanderInterface
     * @throws Exception
     */
    protected function getAuthFailedCommander(
        string $level
    ): SSHCommanderInterface {
        $options = array_merge(
            $this->sshOptions,
            ['user' => '****']
        );

        $logger    = $this->getTestLogger($level);
        $commander = new SSHCommander($options, $logger);

        return $commander;
    }

    public function testCommandOutputIsLoggedOnDebugLevel(): void
    {
        $level = LogLevel::DEBUG;

        $handler = $this->runCommandAndGetLogRecords(
            $level,
            self::EXPECTED_OUTCOME_SUCCESS
        );

        $this->assertEquals(Logger::DEBUG, $handler->getLevel());

        $this->assertTrue(
            $handler->hasRecordThatContains(self::COMMAND_OUTPUT_MARKER, $level)
        );
    }

    public function testCommandOutputIsNotLoggedAboveDebugLevel(): void
    {
        $level = LogLevel::INFO;

        $handler = $this->runCommandAndGetLogRecords(
            $level,
            self::EXPECTED_OUTCOME_SUCCESS
        );

        $this->assertEquals(Logger::INFO, $handler->getLevel());

        $this->assertFalse(
            $handler->hasRecordThatContains(self::COMMAND_OUTPUT_MARKER, $level)
        );
    }

    public function testCommandSuccessIsLoggedOnDebugLevel(): void
    {
        $level = LogLevel::DEBUG;

        $handler = $this->runCommandAndGetLogRecords(
            $level,
            self::EXPECTED_OUTCOME_SUCCESS
        );

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

        $handler = $this->runCommandAndGetLogRecords(
            $level,
            self::EXPECTED_OUTCOME_SUCCESS
        );

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

        $logger    = $this->getTestLogger($level);
        $commander = new SSHCommander($this->sshOptions, $logger);
        $handler   = $commander->getLogger()->popHandler();

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

        $logger    = $this->getTestLogger($level);
        $commander = new SSHCommander($this->sshOptions, $logger);
        $handler   = $commander->getLogger()->popHandler();

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

        $handler = $this->runCommandAndGetLogRecords(
            $level,
            self::EXPECTED_OUTCOME_SUCCESS
        );

        $this->assertEquals(Logger::INFO, $handler->getLevel());

        $regex = $this->getTestOutputStringRegex(self::COMMAND_RUNNING_MARKER);

        $this->assertTrue(
            $handler->hasRecordThatMatches($regex, $level)
        );
    }

    public function testCommandIsNotLoggedAboveInfoLevel(): void
    {
        $level = LogLevel::NOTICE;

        $handler = $this->runCommandAndGetLogRecords(
            $level,
            self::EXPECTED_OUTCOME_SUCCESS
        );

        $this->assertEquals(Logger::NOTICE, $handler->getLevel());

        $regex = $this->getTestOutputStringRegex(self::COMMAND_RUNNING_MARKER);

        $this->assertFalse(
            $handler->hasRecordThatMatches($regex, $level)
        );
    }

    public function testCommandCompletionIsLoggedOnInfoLevel(): void
    {
        $level = LogLevel::INFO;

        $handler = $this->runCommandAndGetLogRecords(
            $level,
            self::EXPECTED_OUTCOME_SUCCESS
        );

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

        $handler = $this->runCommandAndGetLogRecords(
            $level,
            self::EXPECTED_OUTCOME_SUCCESS
        );

        $this->assertEquals(Logger::NOTICE, $handler->getLevel());

        $this->assertFalse(
            $handler->hasRecordThatMatches(
                static::COMMAND_COMPLETION_REGEX,
                $level
            )
        );
    }

    public function testCommandErrorIsLoggedOnNoticeLevel(): void
    {
        $level = LogLevel::NOTICE;

        $handler = $this->runCommandAndGetLogRecords(
            $level,
            self::EXPECTED_OUTCOME_FAILURE
        );

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

        $handler = $this->runCommandAndGetLogRecords(
            $level,
            self::EXPECTED_OUTCOME_FAILURE
        );

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

        $options = array_merge(
            $this->sshOptions,
            ['user' => '****']
        );

        $logger             = $this->getTestLogger($level);
        $exceptionWasThrown = false;

        try {
            new SSHCommander($options, $logger);
        } catch (AuthenticationException $e) {
            $exceptionWasThrown = true;
            /** @var TestHandler $handler */
            $handler = $logger->popHandler();
            $this->assertTrue(
                $handler->hasRecordThatContains(
                    self::LOGIN_FAILED_MARKER,
                    $level
                )
            );
        } finally {
            $this->assertTrue($exceptionWasThrown);
        }
    }

    public function testLoginErrorIsNotLoggedAboveErrorLevel(): void
    {
        $level = LogLevel::CRITICAL;

        $options = array_merge(
            $this->sshOptions,
            ['user' => '****']
        );

        $logger             = $this->getTestLogger($level);
        $exceptionWasThrown = false;

        try {
            new SSHCommander($options, $logger);
        } catch (AuthenticationException $e) {
            $exceptionWasThrown = true;
            /** @var TestHandler $handler */
            $handler = $logger->popHandler();
            $this->assertFalse(
                $handler->hasRecordThatContains(
                    self::LOGIN_FAILED_MARKER,
                    $level
                )
            );
        } finally {
            $this->assertTrue($exceptionWasThrown);
        }
    }

    public function testSeparateStdErrIsLoggedOnDebugLevel(): void
    {
        $level = LogLevel::DEBUG;

        /** @var TestHandler $handler */
        $handler = $this->runCommandAndGetLogRecords(
            $level,
            self::EXPECTED_OUTCOME_FAILURE,
            true, // run in isolated mode
            ['separate_stderr' => true]
        );

        $this->assertTrue(
            $handler->hasRecordThatContains(
                self::COMMAND_STDERR_MARKER,
                $level
            )
        );
    }
}

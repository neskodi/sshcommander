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
use Exception;

class LoggingTest extends IntegrationTestCase
{
    const EXPECTED_OUTCOME_SUCCESS = 'success';
    const EXPECTED_OUTCOME_FAILURE = 'failure';

    const MATCHING_MODE_REGULAR = 'regular';
    const MATCHING_MODE_REGEXP  = 'regexp';

    const RUNNING_MODE_INTERACTIVE = 'interactive';
    const RUNNING_MODE_ISOLATED    = 'isolated';

    const TEST_OUTPUT_STRING = 'quick brown fox jumps over the lazy dog';

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
        $this->getCommander()->setLogger($this->getTestableLogger($level));

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

    protected function runCommandAndCheckMarkerPresent(
        string $level,
        string $outcome,
        string $marker,
        string $runningMode = self::RUNNING_MODE_INTERACTIVE,
        string $matchingMode = self::MATCHING_MODE_REGULAR,
        array $options = []
    ): void {
        $handler = $this->runCommandAndGetLogRecords(
            $level,
            $outcome,
            (self::RUNNING_MODE_ISOLATED == $runningMode),
            $options
        );

        $result = (self::MATCHING_MODE_REGEXP == $matchingMode)
            ? $handler->hasRecordThatMatches($marker, $level)
            : $handler->hasRecordThatContains($marker, $level);

        $this->assertTrue($result);
    }

    protected function runCommandAndCheckMarkerAbsent(
        string $level,
        string $outcome,
        string $marker,
        string $runningMode = self::RUNNING_MODE_INTERACTIVE,
        string $matchingMode = self::MATCHING_MODE_REGULAR,
        array $options = []
    ): void {
        $handler = $this->runCommandAndGetLogRecords(
            $level,
            $outcome,
            (self::RUNNING_MODE_ISOLATED == $runningMode),
            $options
        );

        $result = (self::MATCHING_MODE_REGEXP == $matchingMode)
            ? $handler->hasRecordThatMatches($marker, $level)
            : $handler->hasRecordThatContains($marker, $level);

        $this->assertFalse($result);
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

        $logger    = $this->getTestableLogger($level);
        $commander = new SSHCommander($options, $logger);

        return $commander;
    }

    public function testCommandOutputIsLoggedOnDebugLevel(): void
    {
        // test the interactive runner
        $this->runCommandAndCheckMarkerPresent(
            LogLevel::DEBUG,
            self::EXPECTED_OUTCOME_SUCCESS,
            self::COMMAND_OUTPUT_MARKER
        );

        // test the isolated runner
        $this->runCommandAndCheckMarkerPresent(
            LogLevel::DEBUG,
            self::EXPECTED_OUTCOME_SUCCESS,
            self::COMMAND_OUTPUT_MARKER,
            self::RUNNING_MODE_ISOLATED
        );
    }

    public function testCommandOutputIsNotLoggedAboveDebugLevel(): void
    {
        // test the interactive runner
        $this->runCommandAndCheckMarkerAbsent(
            LogLevel::INFO,
            self::EXPECTED_OUTCOME_SUCCESS,
            self::COMMAND_OUTPUT_MARKER
        );

        // test the isolated runner
        $this->runCommandAndCheckMarkerAbsent(
            LogLevel::INFO,
            self::EXPECTED_OUTCOME_SUCCESS,
            self::COMMAND_OUTPUT_MARKER,
            self::RUNNING_MODE_ISOLATED
        );
    }

    public function testCommandSuccessIsLoggedOnDebugLevel(): void
    {
        // test the interactive runner
        $this->runCommandAndCheckMarkerPresent(
            LogLevel::DEBUG,
            self::EXPECTED_OUTCOME_SUCCESS,
            self::COMMAND_SUCCESS_MARKER
        );

        // test the isolated runner
        $this->runCommandAndCheckMarkerPresent(
            LogLevel::DEBUG,
            self::EXPECTED_OUTCOME_SUCCESS,
            self::COMMAND_SUCCESS_MARKER,
            self::RUNNING_MODE_ISOLATED
        );
    }

    public function testCommandSuccessIsNotLoggedAboveDebugLevel(): void
    {
        // test the interactive runner
        $this->runCommandAndCheckMarkerAbsent(
            LogLevel::INFO,
            self::EXPECTED_OUTCOME_SUCCESS,
            self::COMMAND_SUCCESS_MARKER
        );

        // test the isolated runner
        $this->runCommandAndCheckMarkerAbsent(
            LogLevel::INFO,
            self::EXPECTED_OUTCOME_SUCCESS,
            self::COMMAND_SUCCESS_MARKER,
            self::RUNNING_MODE_ISOLATED
        );
    }

    public function testConnectionStatusIsLoggedOnInfoLevel(): void
    {
        $level = LogLevel::INFO;

        $logger    = $this->getTestableLogger($level);
        $commander = new SSHCommander($this->sshOptions, $logger);
        $handler   = $commander->getLogger()->popHandler();

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

        $logger    = $this->getTestableLogger($level);
        $commander = new SSHCommander($this->sshOptions, $logger);
        $handler   = $commander->getLogger()->popHandler();

        $this->assertFalse(
            $handler->hasRecordThatContains(
                self::LOGIN_SUCCESS_MARKER,
                $level
            )
        );
    }

    public function testCommandIsLoggedOnInfoLevel(): void
    {
        // test the interactive runner
        $this->runCommandAndCheckMarkerPresent(
            LogLevel::INFO,
            self::EXPECTED_OUTCOME_SUCCESS,
            self::COMMAND_RUNNING_MARKER
        );

        // test the isolated runner
        $this->runCommandAndCheckMarkerPresent(
            LogLevel::INFO,
            self::EXPECTED_OUTCOME_SUCCESS,
            self::COMMAND_RUNNING_MARKER,
            self::RUNNING_MODE_ISOLATED
        );
    }

    public function testCommandIsNotLoggedAboveInfoLevel(): void
    {
        // test the interactive runner
        $this->runCommandAndCheckMarkerAbsent(
            LogLevel::NOTICE,
            self::EXPECTED_OUTCOME_SUCCESS,
            self::COMMAND_RUNNING_MARKER
        );

        // test the isolated runner
        $this->runCommandAndCheckMarkerAbsent(
            LogLevel::NOTICE,
            self::EXPECTED_OUTCOME_SUCCESS,
            self::COMMAND_RUNNING_MARKER,
            self::RUNNING_MODE_ISOLATED
        );
    }

    public function testCommandCompletionIsLoggedOnInfoLevel(): void
    {
        // test the interactive runner
        $this->runCommandAndCheckMarkerPresent(
            LogLevel::INFO,
            self::EXPECTED_OUTCOME_SUCCESS,
            self::COMMAND_COMPLETION_REGEX,
            self::RUNNING_MODE_INTERACTIVE,
            self::MATCHING_MODE_REGEXP
        );

        // test the isolated runner
        $this->runCommandAndCheckMarkerPresent(
            LogLevel::INFO,
            self::EXPECTED_OUTCOME_SUCCESS,
            self::COMMAND_COMPLETION_REGEX,
            self::RUNNING_MODE_ISOLATED,
            self::MATCHING_MODE_REGEXP
        );
    }

    public function testCommandCompletionIsNotLoggedAboveInfoLevel(): void
    {
        // test the interactive runner
        $this->runCommandAndCheckMarkerAbsent(
            LogLevel::NOTICE,
            self::EXPECTED_OUTCOME_SUCCESS,
            self::COMMAND_COMPLETION_REGEX,
            self::RUNNING_MODE_INTERACTIVE,
            self::MATCHING_MODE_REGEXP
        );

        // test the isolated runner
        $this->runCommandAndCheckMarkerAbsent(
            LogLevel::NOTICE,
            self::EXPECTED_OUTCOME_SUCCESS,
            self::COMMAND_COMPLETION_REGEX,
            self::RUNNING_MODE_ISOLATED,
            self::MATCHING_MODE_REGEXP
        );
    }

    public function testCommandErrorIsLoggedOnNoticeLevel(): void
    {
        // test the interactive runner
        $this->runCommandAndCheckMarkerPresent(
            LogLevel::NOTICE,
            self::EXPECTED_OUTCOME_FAILURE,
            self::COMMAND_ERROR_MARKER
        );

        // test the isolated runner
        $this->runCommandAndCheckMarkerPresent(
            LogLevel::NOTICE,
            self::EXPECTED_OUTCOME_FAILURE,
            self::COMMAND_ERROR_MARKER,
            self::RUNNING_MODE_ISOLATED
        );
    }

    public function testCommandErrorIsNotLoggedAboveNoticeLevel(): void
    {
        // test the interactive runner
        $this->runCommandAndCheckMarkerAbsent(
            LogLevel::ERROR,
            self::EXPECTED_OUTCOME_FAILURE,
            self::COMMAND_ERROR_MARKER
        );

        // test the isolated runner
        $this->runCommandAndCheckMarkerAbsent(
            LogLevel::ERROR,
            self::EXPECTED_OUTCOME_FAILURE,
            self::COMMAND_ERROR_MARKER,
            self::RUNNING_MODE_ISOLATED
        );
    }

    public function testLoginErrorLoggedOnErrorLevel(): void
    {
        $level = LogLevel::ERROR;

        $options = array_merge(
            $this->sshOptions,
            ['user' => '****']
        );

        $logger             = $this->getTestableLogger($level);
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

        $logger             = $this->getTestableLogger($level);
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

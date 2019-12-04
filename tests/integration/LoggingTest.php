<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\integration;

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

    protected function getSuccessfulCommand()
    {
        if (!$this->successfulCommand) {
            $this->successfulCommand = sprintf(
                'echo "%s"',
                self::TEST_OUTPUT_STRING
            );
        }

        return $this->successfulCommand;
    }

    protected function getUnsuccessfulCommand()
    {
        if (!$this->unsuccessfulCommand) {
            $this->unsuccessfulCommand = 'cd /n0/such/d!r3ct0ry';
        }

        return $this->unsuccessfulCommand;
    }

    protected function getCommander()
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

    protected function getCommandLogRecords(string $level, string $outcome): TestHandler
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

    public function testCommandOutputIsLoggedOnDebugLevel()
    {
        $level = LogLevel::DEBUG;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertTrue(
            $handler->hasRecordThatContains('Command returned:', $level)
        );
    }

    public function testCommandOutputIsNotLoggedAboveDebugLevel()
    {
        $level = LogLevel::INFO;

        $handler = $this->getCommandLogRecords($level, 'success');

        $this->assertFalse(
            $handler->hasRecordThatContains('Command returned:', $level)
        );
    }

    public function testCommandSuccessIsLoggedOnDebugLevel()
    {

    }

    public function testCommandSuccessIsNotLoggedAboveDebugLevel()
    {

    }

    public function testConnectionStatusIsLoggedOnInfoLevel()
    {

    }

    public function testConnectionStatusIsNotLoggedAboveInfoLevel()
    {

    }

    public function testCommandIsLoggedOnInfoLevel()
    {

    }

    public function testCommandIsNotLoggedAboveInfoLevel()
    {

    }

    public function testCommandCompletionIsLoggedOnInfoLevel()
    {

    }

    public function testCommandCompletionIsNotLoggedAboveInfoLevel()
    {

    }

    public function testCommandErrorLoggedOnNoticeLevel()
    {

    }

    public function testCommandErrorIsNotLoggedAboveNoticeLevel()
    {

    }

    public function testLoginErrorLoggedOnErrorLevel()
    {

    }

    public function testLoginErrorIsNotLoggedAboveErrorLevel()
    {

    }

}

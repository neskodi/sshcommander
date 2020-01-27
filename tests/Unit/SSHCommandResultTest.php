<?php /** @noinspection PhpUndefinedMethodInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\Unit;

use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\SSHCommandResult;
use Neskodi\SSHCommander\Tests\TestCase;
use Neskodi\SSHCommander\SSHCommand;

class SSHCommandResultTest extends TestCase
{
    const RESULT_DELIMITER_LINE  = '---';
    const RESULT_MESSAGE_SUCCESS = 'Command returned exit status: %s (code 0)';
    const RESULT_MESSAGE_ERROR   = 'Command returned error code: %d';

    protected function createCommandResult(
        string $command = 'ls'
    ): SSHCommandResultInterface {
        return new SSHCommandResult($this->getCommand($command));
    }

    protected function getCommand(string $command = 'ls'): SSHCommandInterface
    {
        $defaultConfig = $this->getTestConfigAsArray();
        $command       = new SSHCommand($command, $defaultConfig);

        return $command;
    }

    protected function getCommandSuccessMessage(): string
    {
        return sprintf(
            static::RESULT_MESSAGE_SUCCESS,
            SSHCommandResult::STATUS_OK
        );
    }

    protected function getCommandErrorMessage(int $code): string
    {
        return sprintf(static::RESULT_MESSAGE_ERROR, $code);
    }

    protected function getExpectedDebugOutput(
        int $exitCode,
        array $output,
        array $error
    ): array {

        $message = (0 === $exitCode)
            ? $this->getCommandSuccessMessage()
            : $this->getCommandErrorMessage($exitCode);

        $acc     = [];

        $outhead = 'Command returned:';
        $errhead = 'Command STDERR:';

        array_push($acc, $message);
        array_push($acc, $outhead);
        array_push($acc, ...$output);
        array_push($acc, static::RESULT_DELIMITER_LINE);
        array_push($acc, $errhead);
        array_push($acc, ...$error);
        array_push($acc, static::RESULT_DELIMITER_LINE);

        return $acc;
    }

    public function testConstructor(): void
    {
        $result = $this->createCommandResult();

        $this->assertInstanceOf(SSHCommandResultInterface::class, $result);

        $this->assertNull($result->getExitCode());
        $this->assertNull($result->getStatus());
        $this->assertNull($result->isError());
        $this->assertNull($result->isOk());
        $this->assertNull($result->getOutput());
        $this->assertNull($result->getErrorOutput());
    }

    public function testConstructorWithSuccessfulExitCode(): void
    {
        $command = $this->getCommand();
        $result = new SSHCommandResult($command, 0);

        $this->assertSame(0, $result->getExitCode());
        $this->assertSame(SSHCommandResult::STATUS_OK, $result->getStatus());
        $this->assertFalse($result->isError());
        $this->assertTrue($result->isOk());
        $this->assertNull($result->getOutput());
        $this->assertNull($result->getErrorOutput());
    }

    public function testConstructorWithErrorExitCode(): void
    {
        $command = $this->getCommand();
        $result = new SSHCommandResult($command, 255);

        $this->assertSame(255, $result->getExitCode());
        $this->assertSame(SSHCommandResult::STATUS_ERROR, $result->getStatus());
        $this->assertTrue($result->isError());
        $this->assertFalse($result->isOk());
        $this->assertNull($result->getOutput());
        $this->assertNull($result->getErrorOutput());
    }

    public function testConstructorWithStdout(): void
    {
        $command = $this->getCommand();
        $result = new SSHCommandResult($command, null, ['test']);

        $this->assertNull($result->getExitCode());
        $this->assertNull($result->getStatus());
        $this->assertNull($result->isError());
        $this->assertNull($result->isOk());
        $this->assertEquals(['test'], $result->getOutput());
        $this->assertNull($result->getErrorOutput());
    }

    public function testConstructorWithStderr(): void
    {
        $command = $this->getCommand();
        $result = new SSHCommandResult($command, null, null, ['test']);

        $this->assertNull($result->getExitCode());
        $this->assertNull($result->getStatus());
        $this->assertNull($result->isError());
        $this->assertNull($result->isOk());
        $this->assertNull($result->getOutput());
        $this->assertEquals(['test'], $result->getErrorOutput());
    }

    public function testRemoveLastEmptyLine(): void
    {
        $defaultConfig = $this->getTestConfigAsArray();
        $defaultConfig['output_trim_last_empty_line'] = true;

        $command       = new SSHCommand('ls', $defaultConfig);

        $result = new SSHCommandResult(
            $command,
            0,
            ['test out', ''],
            ['test err', '']
        );

        $this->assertEquals(['test out'], $result->getOutput());
        $this->assertEquals(['test err'], $result->getErrorOutput());
    }

    public function testLeaveLastEmptyLine(): void
    {
        $defaultConfig = $this->getTestConfigAsArray();
        $defaultConfig['output_trim_last_empty_line'] = false;

        $command       = new SSHCommand('ls', $defaultConfig);

        $result = new SSHCommandResult(
            $command,
            0,
            ['test out', ''],
            ['test err', '']
        );

        $this->assertEquals(['test out', ''], $result->getOutput());
        $this->assertEquals(['test err', ''], $result->getErrorOutput());
    }

    public function test__toString(): void
    {
        $result = $this->createCommandResult();
        $result->setOutput([
            'test line 1',
            'test line 2',
        ]);
        $result->setOutputDelimiter(';');

        $str = (string)$result;

        $this->assertEquals('test line 1;test line 2', $str);
    }

    public function testSetCommand(): void
    {
        $result = $this->createCommandResult();
        $result->setCommand(
            new SSHCommand(
                'cd /test',
                $this->getTestConfigAsArray()
            )
        );

        $strCommand = $result->getCommand()->getCommand();

        $this->assertEquals('cd /test', $strCommand);
    }

    public function testSetExitCode(): void
    {
        $exitCode = 4;

        $result = $this->createCommandResult();
        $result->setExitCode($exitCode);

        $this->assertEquals($exitCode, $result->getExitCode());
    }

    public function testSetOutputDelimiter(): void
    {
        $result = $this->createCommandResult();
        $result->setOutput([
            'test line 1',
            'test line 2',
        ]);
        $result->setOutputDelimiter('~~');

        $str = (string)$result;

        $this->assertEquals('test line 1~~test line 2', $str);
    }

    public function testIsError(): void
    {
        $result = $this->createCommandResult();

        $result->setExitCode(255);
        $this->assertTrue($result->isError());
        $result->setExitCode(0);
        $this->assertFalse($result->isError());
    }

    public function testIsOk(): void
    {
        $result = $this->createCommandResult();

        $result->setExitCode(0);
        $this->assertTrue($result->isOk());
        $result->setExitCode(255);
        $this->assertFalse($result->isOk());
    }

    public function testSetOutput(): void
    {
        $output = [
            'test line 1',
            'test line 2',
        ];

        $result = $this->createCommandResult();
        $result->setOutput($output);

        $this->assertEquals($output, $result->getOutput());
    }

    public function testSetErrorOutput(): void
    {
        $output = [
            'test error line 1',
            'test error line 2',
        ];

        $result = $this->createCommandResult();
        $result->setErrorOutput($output);
        $result->setOutputDelimiter(';');

        $this->assertEquals($output, $result->getErrorOutput());
        $this->assertEquals(
            'test error line 1;test error line 2',
            $result->getErrorOutput(true)
        );
    }

    public function testLogResult(): void
    {
        $output = [
            'test stdout line 1',
            'test stdout line 2',
        ];

        $error = [
            'test stderr line 1',
            'test stderr line 2',
        ];

        $exitCode = 0;

        $result = $this->createCommandResult();

        $result->setExitCode($exitCode);
        $result->setOutput($output);
        $result->setErrorOutput($error);

        $result->setLogger($this->createTestLogger());

        $result->logResult();
        $handler = $result->getLogger()->popHandler();

        $records = $handler->getRecords();
        $records = array_column($records, 'message');

        $this->assertEquals(
            $this->getExpectedDebugOutput($exitCode, $output, $error),
            $records
        );
    }

    public function testLogEmptyOutput(): void
    {
        $result = $this->createCommandResult();
        $result->setOutput([]);
        $result->setExitCode(0);
        $result->setLogger($this->createTestLogger());

        $result->logResult();
        $handler = $result->getLogger()->popHandler();

        $records = $handler->getRecords();
        $records = array_column($records, 'message');

        $this->assertEquals(
            [
                $this->getCommandSuccessMessage(),
                'Command output was empty.',
                static::RESULT_DELIMITER_LINE,
            ],
            $records
        );
    }

    public function testGetStatus(): void
    {
        $result = $this->createCommandResult();

        $result->setExitCode(0);
        $this->assertEquals(
            SSHCommandResult::STATUS_OK,
            $result->getStatus()
        );

        $result->setExitCode(255);
        $this->assertEquals(
            SSHCommandResult::STATUS_ERROR,
            $result->getStatus()
        );
    }
}


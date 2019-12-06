<?php /** @noinspection PhpUndefinedMethodInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\unit;

use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\SSHCommandResult;
use Neskodi\SSHCommander\Tests\TestCase;
use Neskodi\SSHCommander\SSHCommand;
use Psr\Log\LogLevel;

class SSHCommandResultTest extends TestCase
{
    protected function createCommandResult(string $command = 'ls'): SSHCommandResultInterface
    {
        $defaultConfig = $this->getTestConfigAsArray();
        $command       = new SSHCommand($command, $defaultConfig);
        $result        = new SSHCommandResult($command);

        return $result;
    }

    public function testConstructor()
    {
        $result = $this->createCommandResult();

        $this->assertInstanceOf(SSHCommandResultInterface::class, $result);
    }

    public function test__toString()
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

    public function testSetCommand()
    {
        $result = $this->createCommandResult();
        $result->setCommand(
            new SSHCommand(
                'cd /test',
                $this->getTestConfigAsArray()
            )
        );

        $strCommand = $result->getCommand()->getCommands(true, false);

        $this->assertEquals('cd /test', $strCommand);
    }

    public function testSetExitCode()
    {
        $exitCode = 4;

        $result = $this->createCommandResult();
        $result->setExitCode($exitCode);

        $this->assertEquals($exitCode, $result->getExitCode());
    }

    public function testSetOutputDelimiter()
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

    public function testIsError()
    {
        $result = $this->createCommandResult();

        $result->setExitCode(255);
        $this->assertTrue($result->isError());
        $result->setExitCode(0);
        $this->assertFalse($result->isError());
    }

    public function testIsOk()
    {
        $result = $this->createCommandResult();

        $result->setExitCode(0);
        $this->assertTrue($result->isOk());
        $result->setExitCode(255);
        $this->assertFalse($result->isOk());
    }

    public function testSetOutput()
    {
        $output = [
            'test line 1',
            'test line 2',
        ];

        $result = $this->createCommandResult();
        $result->setOutput($output);

        $this->assertEquals($output, $result->getOutput());
    }

    public function testSetErrorOutput()
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

    public function testLogResult()
    {
        $output = [
            'test stdout line 1',
            'test stdout line 2',
        ];

        $error = [
            'test stderr line 1',
            'test stderr line 2',
        ];

        $result = $this->createCommandResult();
        $result->setOutput($output);
        $result->setErrorOutput($error);
        $result->setLogger($this->getTestLogger(LogLevel::DEBUG));

        $result->logResult();
        $handler = $result->getLogger()->popHandler();

        $records = $handler->getRecords();
        $records = array_column($records, 'message');

        $this->assertEquals(
            $this->getExpectedDebugOutput($output, $error),
            $records
        );
    }

    public function testLogEmptyOutput()
    {
        $result = $this->createCommandResult();
        $result->setOutput([]);
        $result->setLogger($this->getTestLogger(LogLevel::DEBUG));

        $result->logResult();
        $handler = $result->getLogger()->popHandler();

        $records = $handler->getRecords();
        $records = array_column($records, 'message');

        $this->assertEquals(
            ['Command output was empty.', '---'],
            $records
        );
    }

    public function testGetStatus()
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

    protected function getExpectedDebugOutput(array $output, array $error): array
    {
        $acc     = ['Command returned:'];
        $errhead = 'Command STDERR:';
        $delim   = '---';

        array_push($acc, ...$output);
        array_push($acc, $delim);
        array_push($acc, $errhead);
        array_push($acc, ...$error);
        array_push($acc, $delim);

        return $acc;
    }
}


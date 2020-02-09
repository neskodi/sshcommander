<?php /** @noinspection PhpParamsInspection */

/** @noinspection DuplicatedCode */

namespace Neskodi\SSHCommander\Tests\Unit;

use Neskodi\SSHCommander\Interfaces\SSHResultCollectionInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandResultInterface;
use Neskodi\SSHCommander\SSHResultCollection;
use Neskodi\SSHCommander\SSHCommandResult;
use Neskodi\SSHCommander\Tests\TestCase;
use Neskodi\SSHCommander\SSHCommand;

class SSHResultCollectionTest extends TestCase
{
    public function testArrayAccess(): void
    {
        $collection = $this->createResultCollection([
            ['ls', 0],
            ['pwd', 0],
        ]);

        $this->assertInstanceOf(SSHCommandResultInterface::class, $collection[0]);
        $this->assertInstanceOf(SSHCommandResultInterface::class, $collection[1]);
        $this->assertNull($collection[2]);
        $this->assertFalse(isset($collection[2]));
    }

    public function testArrayIteration(): void
    {
        $collection = $this->createResultCollection([
            ['ls', 0],
            ['pwd', 0],
        ]);

        foreach ($collection as $key => $item) {
            $this->assertIsInt($key);
            $this->assertInstanceOf(SSHCommandResultInterface::class, $item);
        }

        $this->assertEquals(1, $key);
    }

    public function testArrayCount(): void
    {
        $collection = $this->createResultCollection([
            ['ls', 0],
            ['pwd', 0],
        ]);

        $this->assertCount(2, $collection);
    }

    public function testMap(): void
    {
        $collection = $this->createResultCollection([
            ['test', 0, 'empty line'],
            ['test', 1, 'text line 1'],
        ]);

        $newCollection = $collection->map(function ($v) {
            return ucwords($v);
        });

        $this->assertCount(2, $newCollection);
        $this->assertEquals('Empty Line', $newCollection[0]);
        $this->assertEquals('Text Line 1', $newCollection[1]);
    }

    public function testFilter(): void
    {
        $collection = $this->createResultCollectionFromOutputStrings([
            'empty line',
            'text line 1',
            'test line 2',
            'test line 3',
        ]);

        $filter = function (SSHCommandResultInterface $result) {
            return preg_match('/line [23]/', (string)$result);
        };

        $filtered = $collection->filter($filter);

        $this->assertCount(2, $filtered);
        $this->assertInstanceOf(SSHCommandResultInterface::class, $filtered[0]);
        $this->assertEquals('test line 2', (string)$filtered[0]);
        $this->assertInstanceOf(SSHCommandResultInterface::class, $filtered[1]);
        $this->assertEquals('test line 3', (string)$filtered[1]);
    }

    public function testCount(): void
    {
        $collection = $this->createResultCollection([
            ['pwd', 0],
            ['cd', 1],
        ]);

        $this->assertEquals(2, $collection->count());

        $countCallback = function (SSHCommandResultInterface $result) {
            return 'cd' === (string)$result->getCommand();
        };

        $this->assertEquals(1, $collection->count($countCallback));
    }

    public function testAll(): void
    {
        $results = [
            $this->createSSHCommandResult('pwd', 0, 'empty line'),
            $this->createSSHCommandResult('pwd', 0, 'text line 1'),
        ];

        $collection = new SSHResultCollection;

        foreach ($results as $result) {
            $collection[] = $result;
        }

        $all = $collection->all();
        $this->assertIsArray($all);
        $this->assertEquals($results, $all);
    }

    public function testFirst(): void
    {
        $collection = $this->createResultCollection([
            ['pwd', 0],
            ['cd', 1],
        ]);

        $result = $collection->first();

        $this->assertInstanceOf(SSHCommandResultInterface::class, $result);
        $this->assertEquals('pwd', (string)$result->getCommand());
    }

    public function testLast(): void
    {
        $collection = $this->createResultCollection([
            ['pwd', 0],
            ['cd', 1],
        ]);

        $result = $collection->last();

        $this->assertInstanceOf(SSHCommandResultInterface::class, $result);
        $this->assertEquals('cd', (string)$result->getCommand());
    }

    public function testSuccessful(): void
    {
        $collection = $this->createResultCollection([
            ['pwd', 0],
            ['cd', 1],
        ]);

        $successful = $collection->successful();

        $this->assertInstanceOf(SSHResultCollectionInterface::class, $successful);
        $this->assertTrue($successful->isOk());
        $this->assertCount(1, $successful);
        $this->assertEquals('pwd', (string)$successful->first()->getCommand());
    }

    public function testFailed(): void
    {
        $collection = $this->createResultCollection([
            ['pwd', 0],
            ['cd', 1],
        ]);

        $failed = $collection->failed();

        $this->assertInstanceOf(SSHResultCollectionInterface::class, $failed);
        $this->assertFalse($failed->isOk());
        $this->assertCount(1, $failed);
        $this->assertEquals('cd', (string)$failed->first()->getCommand());
    }

    public function testIsOk(): void
    {
        $collection = $this->createResultCollection([
            ['pwd', 0],
            ['cd', 1],
        ]);

        $this->assertFalse($collection->isOk());

        $successful = $collection->successful();

        $this->assertInstanceOf(SSHResultCollectionInterface::class, $successful);
        $this->assertTrue($successful->isOk());
    }

    public function testIsError(): void
    {
        $collection = $this->createResultCollection([
            ['pwd', 0],
            ['cd', 1],
        ]);

        $this->assertTrue($collection->isError());

        $successful = $collection->successful();

        $this->assertInstanceOf(SSHResultCollectionInterface::class, $successful);
        $this->assertFalse($successful->isError());
    }

    public function testClear(): void
    {
        $collection = $this->createResultCollection([
            ['pwd', 0, 'empty line'],
            ['pwd', 0, 'text line 1'],
            ['pwd', 1, 'test line 2'],
            ['pwd', 1, 'test line 3'],
        ]);

        $collection->clear();

        $this->assertCount(0, $collection);
    }

    public function testHasResultsThatMatch(): void
    {
        $collection = new SSHResultCollection;

        $collection[] = $this->createSSHCommandResult(
            'pwd',
            0,
            "test line 1\ntest line 2"
        );

        $this->assertTrue($collection->hasResultsThatMatch('/test line [0-9]/'));
        $this->assertFalse($collection->hasResultsThatMatch('/the sad truth/'));
    }

    public function testHasResultsThatContain(): void
    {
        $collection = new SSHResultCollection;

        $collection[] = $this->createSSHCommandResult(
            'pwd',
            0,
            "test line 1\ntest line 2"
        );

        $this->assertTrue($collection->hasResultsThatContain(
            'test line',
            SSHResultCollection::MATCHING_MODE_STRING_CS)
        );

        $this->assertFalse($collection->hasResultsThatContain(
            'test LINE',
            SSHResultCollection::MATCHING_MODE_STRING_CS)
        );

        $this->assertTrue($collection->hasResultsThatContain(
            'test LINE',
            SSHResultCollection::MATCHING_MODE_STRING_CI)
        );

        $this->assertFalse($collection->hasResultsThatContain(
            'uranium ore',
            SSHResultCollection::MATCHING_MODE_STRING_CI)
        );
    }

    public function testHasFailedResults(): void
    {
        $collection = $this->createResultCollection([
            ['ls', 0],
            ['pwd', 0],
        ]);

        $this->assertFalse($collection->hasFailedResults());

        $collection[] = $this->createSSHCommandResult('cd /nodir', 1);

        $this->assertTrue($collection->hasFailedResults());
    }

    public function testHasSuccessfulResults(): void
    {
        $collection = $this->createResultCollection([
            ['pwd', 0],
            ['cd', 1],
        ]);

        $this->assertTrue($collection->hasSuccessfulResults());

        $failed = $collection->failed();

        $this->assertFalse($failed->hasSuccessfulResults());
    }

    public function testCountFailedResults(): void
    {
        $collection = $this->createResultCollection([
            ['ls', 1],
            ['pwd', 0],
            ['cd', 1],
        ]);

        $this->assertEquals(2, $collection->countFailedResults());
    }

    public function testCountSuccessfulResults(): void
    {
        $collection = $this->createResultCollection([
            ['pwd', 0],
            ['cd', 1],
            ['ls', 2],
            ['grep', 0],
        ]);

        $this->assertEquals(2, $collection->countSuccessfulResults());
    }

    public function testGetResultsThatMatch(): void
    {
        $collection = $this->createResultCollectionFromOutputStrings([
            'empty line',
            'text line 1',
            'test line 2',
            'test line 3',
        ]);

        $newCollection = $collection->getResultsThatMatch('/te[sx]t\sline/');

        $this->assertCount(3, $newCollection);
        $this->assertEquals('text line 1', (string)$newCollection[0]);
        $this->assertEquals('test line 2', (string)$newCollection[1]);
        $this->assertEquals('test line 3', (string)$newCollection[2]);
    }

    public function testGetFirstResultThatMatches(): void
    {
        $collection = $this->createResultCollectionFromOutputStrings([
            'empty line',
            'text line 1',
            'test line 2',
            'test line 3',
        ]);

        $result = $collection->getFirstResultThatMatches('/te[sx]t\sline/');

        $this->assertInstanceOf(SSHCommandResultInterface::class, $result);
        $this->assertEquals('text line 1', (string)$result);
    }

    public function testGetLastResultThatMatches(): void
    {
        $collection = $this->createResultCollectionFromOutputStrings([
            'empty line',
            'text line 1',
            'test line 2',
            'test line 3',
        ]);

        $result = $collection->getLastResultThatMatches('/te[sx]t\sline/');

        $this->assertInstanceOf(SSHCommandResultInterface::class, $result);
        $this->assertEquals('test line 3', (string)$result);
    }

    public function testGetResultsThatContain(): void
    {
        $collection = $this->createResultCollectionFromOutputStrings([
            'empty line',
            'text line 1',
            'test line 2',
            'test line 3',
        ]);

        $newCollection = $collection->getResultsThatContain('test line');

        $this->assertCount(2, $newCollection);
        $this->assertEquals('test line 2', (string)$newCollection[0]);
        $this->assertEquals('test line 3', (string)$newCollection[1]);
    }

    public function testGetFirstResultThatContains(): void
    {
        $collection = $this->createResultCollectionFromOutputStrings([
            'empty line',
            'text line 1',
            'test line 2',
            'test line 3',
        ]);

        $result = $collection->getFirstResultThatContains('test line');

        $this->assertInstanceOf(SSHCommandResultInterface::class, $result);
        $this->assertEquals('test line 2', (string)$result);
    }

    public function testGetLastResultThatContains(): void
    {
        $collection = $this->createResultCollectionFromOutputStrings([
            'empty line',
            'text line 1',
            'test line 2',
            'test line 3',
        ]);

        $result = $collection->getLastResultThatContains('test line');

        $this->assertInstanceOf(SSHCommandResultInterface::class, $result);
        $this->assertEquals('test line 3', (string)$result);
    }

    public function testGetFirstFailedResult(): void
    {
        $collection = $this->createResultCollection([
            ['test', 0, 'empty line'],
            ['test', 1, 'text line 1'],
            ['test', 1, 'test line 2'],
        ]);

        $collection[] = $this->createSSHCommandResult();
        $collection[] = $this->createSSHCommandResult();
        $collection[] = $this->createSSHCommandResult();

        $result = $collection->getFirstFailedResult();

        $this->assertInstanceOf(SSHCommandResultInterface::class, $result);
        $this->assertEquals('text line 1', (string)$result);
    }

    public function testGetLastFailedResult(): void
    {
        $collection = $this->createResultCollection([
            ['pwd', 1, 'empty line'],
            ['pwd', 0, 'text line 1'],
            ['pwd', 1, 'test line 2'],
            ['pwd', 0, 'test line 3'],
        ]);

        $result = $collection->getLastFailedResult();

        $this->assertInstanceOf(SSHCommandResultInterface::class, $result);
        $this->assertEquals('test line 2', (string)$result);
    }

    public function testGetFirstSuccessfulResult(): void
    {
        $collection = $this->createResultCollection([
            ['pwd', 1, 'empty line'],
            ['pwd', 0, 'text line 1'],
            ['pwd', 1, 'test line 2'],
            ['pwd', 0, 'test line 3'],
        ]);

        $result = $collection->getFirstSuccessfulResult();

        $this->assertInstanceOf(SSHCommandResultInterface::class, $result);
        $this->assertEquals('text line 1', (string)$result);
    }

    public function testGetLastSuccessfulResult(): void
    {
        $collection = $this->createResultCollection([
            ['pwd', 0, 'empty line'],
            ['pwd', 0, 'text line 1'],
            ['pwd', 1, 'test line 2'],
            ['pwd', 1, 'test line 3'],
        ]);

        $result = $collection->getLastSuccessfulResult();

        $this->assertInstanceOf(SSHCommandResultInterface::class, $result);
        $this->assertEquals('text line 1', (string)$result);
    }

    /**
     * Create a single command result object from the provided command, exit code,
     * and output text.
     *
     * @param string $command
     * @param int    $exitCode
     * @param string $stdout
     * @param string $stderr
     *
     * @return SSHCommandResultInterface
     */
    protected function createSSHCommandResult(
        string $command = 'test',
        int $exitCode = 0,
        string $stdout = '',
        string $stderr = ''
    ): SSHCommandResultInterface {
        $config      = $this->getTestConfigAsArray();
        $sshCommand  = new SSHCommand($command, $config);
        $outputLines = preg_split('/[\r\n]+/', $stdout);
        $errorLines  = preg_split('/[\r\n]+/', $stderr);

        return new SSHCommandResult($sshCommand, $exitCode, $outputLines, $errorLines);
    }

    /**
     * Create a result collection from an array of commands, their exit codes
     * and output strings.
     *
     * @param array $items
     *
     * @return SSHResultCollection
     */
    protected function createResultCollection(array $items = []): SSHResultCollection
    {
        $collection = new SSHResultCollection;

        foreach ($items as $commandData) {
            $command      = $commandData[0] ?? 'test';
            $exitcode     = $commandData[1] ?? 0;
            $output       = $commandData[2] ?? 'test line';
            $collection[] = $this->createSSHCommandResult($command, $exitcode, $output);
        }

        return $collection;
    }

    /**
     * This function lets you only pass output strings, if command itself and
     * its exit code are unimportant.
     *
     * @param array $strings
     *
     * @return SSHResultCollection
     */
    protected function createResultCollectionFromOutputStrings(
        array $strings = []
    ): SSHResultCollection {
        $fullSets = [];

        foreach ($strings as $string) {
            $fullSets[] = ['test', 0, $string];
        }

        return $this->createResultCollection($fullSets);
    }
}

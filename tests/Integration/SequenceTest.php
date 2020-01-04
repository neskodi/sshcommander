<?php /** @noinspection PhpRedundantCatchClauseInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Interfaces\SSHResultCollectionInterface;
use Neskodi\SSHCommander\Exceptions\CommandRunException;
use Neskodi\SSHCommander\Tests\IntegrationTestCase;
use Neskodi\SSHCommander\SSHResultCollection;
use Neskodi\SSHCommander\SSHCommander;

class SequenceTest extends IntegrationTestCase
{
    const ENABLE_LOG = false;

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testSequenceBasedir(): void
    {
        $host = new SSHCommander($this->getDefaultConfig());

        $sequenceConfig = [
            'basedir'        => '/tmp',
            'break_on_error' => false,
        ];

        $results = $host->sequence(
            function ($host) {
                $host->run('pwd');
                $host->run('cd /usr');
                $host->run('pwd');
                $host->run('ls -lA');
                $host->run('pwd');
            },

            $sequenceConfig
        );

        $this->assertSame(0, $results[0]->getExitCode());
        $this->assertSame('/tmp', $results[0]->getOutput(true));

        $this->assertSame(0, $results[1]->getExitCode());

        $this->assertSame(0, $results[2]->getExitCode());
        $this->assertSame('/usr', $results[2]->getOutput(true));

        $this->assertSame(0, $results[3]->getExitCode());

        $this->assertSame(0, $results[4]->getExitCode());
        $this->assertSame('/usr', $results[4]->getOutput(true));
    }

    public function testSequenceBreakOnErrorTrue(): void
    {
        $host = new SSHCommander($this->getDefaultConfig());

        $sequenceConfig = ['break_on_error' => true];

        try {
            $results = $host->sequence(
                function ($host) {
                    $host->run('echo A;');
                    $host->run('cd /no/such/dir');
                    $host->run('echo B');
                },

                $sequenceConfig
            );
        } catch (CommandRunException $e) {
            $results = $host->getCommandRunner()->getResultCollection();
        }

        $this->checkSequenceUnfinished($results, 2, 'B');
    }

    public function testSequenceBreakOnErrorFalse(): void
    {
        $host = new SSHCommander($this->getDefaultConfig());

        $sequenceConfig = ['break_on_error' => false];

        $results = $host->sequence(
            function ($host) {
                $host->run('echo A;');
                $host->run('cd /no/such/dir');
                $host->run('echo B');
            },

            $sequenceConfig
        );

        $this->checkSequenceFinished($results, 3, 'B');

        $this->assertSame(1, $results[1]->getExitCode());
    }

    /**
     * Disable break_on_error globally but enable it per command
     */
    public function testSequenceBreakOnErrorPerCommandTrue(): void
    {
        $host = new SSHCommander($this->getDefaultConfig());

        $sequenceConfig = ['break_on_error' => false];

        try {
            $results = $host->sequence(
                function ($host) {
                    $host->run('echo A;');
                    $host->run('cd /no/such/dir; echo X', ['break_on_error' => true]);
                    $host->run('echo B');
                },

                $sequenceConfig
            );
        } catch (CommandRunException $e) {
            $results = $host->getCommandRunner()->getResultCollection();
        }

        $this->checkSequenceFinished($results, 3, 'B');

        $this->assertFalse($results->hasResultsThatMatch(
            'X',
            SSHResultCollection::MATCHING_MODE_STRING_CS
        ));
    }

    /**
     * Enable break_on_error globally but disable it per command
     */
    public function testSequenceBreakOnErrorPerCommandFalse(): void
    {
        $host = new SSHCommander($this->getDefaultConfig());

        $sequenceConfig = ['break_on_error' => true];

        try {
            $results = $host->sequence(
                function ($host) {
                    $host->run('echo A;');
                    $host->run('cd /no/such/dir; echo X', ['break_on_error' => false]);
                    $host->run('echo B');
                    $host->run('cd /no/such/dir');
                    $host->run('echo C');
                },

                $sequenceConfig
            );
        } catch (CommandRunException $e) {
            $results = $host->getCommandRunner()->getResultCollection();
        }

        $this->checkSequenceUnfinished($results, 4, 'C');
        $this->assertTrue($results->hasResultsThatMatch(
            '/^X$/m',
            SSHResultCollection::MATCHING_MODE_REGEX
        ));
    }

    protected function getDefaultConfig(): array
    {
        $config = $this->sshOptions;

        if (self::ENABLE_LOG) {
            $config = array_merge($config, [
                'log_file'  => 'i:/Code/02. Sandbox/sshcommander/logs/log.txt',
                'log_level' => 'debug',
            ]);
        }

        return $config;
    }

    protected function checkSequenceFinished(
        SSHResultCollectionInterface $results,
        int $expectedCount,
        string $lastExpectedOutput
    ): void {
        $this->assertEquals($expectedCount, $results->count());
        $this->assertSame(
            $lastExpectedOutput,
            $results[$expectedCount - 1]->getOutput(true)
        );
    }

    protected function checkSequenceUnfinished(
        SSHResultCollectionInterface $results,
        int $expectedCount,
        string $mustNotContainOutput
    ): void {
        $this->assertEquals($expectedCount, $results->count());
        $this->assertFalse($results->hasResultsThatMatch(
            $mustNotContainOutput,
            SSHResultCollection::MATCHING_MODE_STRING_CS
        ));
    }
}

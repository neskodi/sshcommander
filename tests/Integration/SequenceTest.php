<?php

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\SSHCommander;
use PHPUnit\Framework\TestCase;

class SequenceTest extends TestCase
{
    /** @noinspection PhpUnhandledExceptionInspection */
    public function testSequence()
    {
        $host = new SSHCommander([
            'host'    => '192.168.11.2',
            'user'    => 'vagrant',
            'keyfile' => 'i:/Dropbox/Personal/Documents and Settings/Keys/SSH/sn',

            'log_file' => 'i:/Code/02. Sandbox/sshcommander/logs/log.txt',
            'log_level' => 'debug',

            'break_on_error' => false,
        ]);

        $results = $host->sequence(function ($host) {
            $host->run('pwd');
            $host->run('cd /usr');
            $host->run('pwd');
            $host->run('ls -lA');
            $host->run('pwd');
            $host->run('cd /no/such/dir');
            $host->run('export A="lewolf"');
            $host->run('echo $A');
        }, ['basedir' => '/tmp']);

        $this->assertSame(0, $results[0]->getExitCode());
        $this->assertSame('/tmp', $results[0]->getOutput(true));

        $this->assertSame(0, $results[1]->getExitCode());

        $this->assertSame(0, $results[2]->getExitCode());
        $this->assertSame('/usr', $results[2]->getOutput(true));

        $this->assertSame(0, $results[3]->getExitCode());

        $this->assertSame(0, $results[4]->getExitCode());
        $this->assertSame('/usr', $results[4]->getOutput(true));

        // $this->assertSame(1, $results[5]->getExitCode());

        $this->assertSame(0, $results[6]->getExitCode());
        $this->assertSame('lewolf', $results[7]->getOutput(true));
    }
}

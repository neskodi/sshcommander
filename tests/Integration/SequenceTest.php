<?php

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\SSHCommander;
use PHPUnit\Framework\TestCase;

class SequenceTest extends TestCase
{
    public function testSequence()
    {
        $host = new SSHCommander([
            'host'    => '192.168.11.2',
            'user'    => 'vagrant',
            'keyfile' => 'i:/Dropbox/Personal/Documents and Settings/Keys/SSH/sn',

            'log_file' => 'i:/Code/02. Sandbox/sshcommander/logs/log.txt',
            'log_level' => 'debug',

            'break_on_error' => false,
            'delimiter_split_output' => "\r\n",
        ]);

        $results = $host->sequence(function ($host) {
            $host->run('cd /tmp');
            $host->run('pwd');
            $host->run('ls -lA');
            $host->run('cd /no/such/dir');
            $host->run('export A="lewolf"');
            $host->run('echo $A');
        });

        $this->assertSame(0, $results[0]->getExitCode());
        $this->assertSame('/tmp', $results[1]->getOutput(true));
        $this->assertSame(0, $results[2]->getExitCode());
        $this->assertSame(0, $results[4]->getExitCode());
        $this->assertSame('lewolf', $results[5]->getOutput(true));
        $this->assertNotSame(0, $results[3]->getExitCode());
    }
}

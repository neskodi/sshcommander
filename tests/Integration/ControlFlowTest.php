<?php

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Tests\IntegrationTestCase;
use Neskodi\SSHCommander\SSHConfig;


class ControlFlowTest extends IntegrationTestCase
{
    protected $tailOutput;

    public function testTerminateCommand()
    {
        $this->tailOutput = '';
        $behavior = function (SSHConnectionInterface $connection) {
            $connection->terminateCommand();
            // read the final output produced by the command when terminating
            $connection->getSSH2()->setTimeout(1);
            $this->tailOutput = $connection->getSSH2()->read();
        };

        $commander = $this->getSSHCommander($this->sshOptions);

        // let the command run for 2 seconds
        $timeoutValue = 1;
        $commander->timeout(
            $timeoutValue,
            SSHConfig::TIMEOUT_CONDITION_RUNTIME,
            $behavior
        );

        $result = $commander->run('ping 127.0.0.1');

        // TODO: do not include timeout handling time into command elapsed time
        // $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());

        // We can assert that the command has been terminated by analyzing the
        // tail output after we sent CTRL+C
        $this->assertStringContainsString('ping statistics', $this->tailOutput);
    }

    public function testSuspendCommand()
    {
        $this->tailOutput = '';
        $behavior = function (SSHConnectionInterface $connection) {
            $connection->suspendCommand();
            // read the final output produced by the command when terminating
            $connection->getSSH2()->setTimeout(1);
            $this->tailOutput = $connection->getSSH2()->read();
        };

        $commander = $this->getSSHCommander($this->sshOptions);

        // let the command run for 2 seconds
        $timeoutValue = 1;
        $commander->timeout(
            $timeoutValue,
            SSHConfig::TIMEOUT_CONDITION_RUNTIME,
            $behavior
        );

        $command = 'ping 127.0.0.1';

        $result = $commander->run($command);

        // TODO: do not include timeout handling time into command elapsed time
        // $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());

        // We can assert that the command has been terminated by analyzing the
        // tail output after we sent CTRL+Z
        $this->assertStringContainsStringIgnoringCase('stopped', $this->tailOutput);
        $this->assertStringContainsString($command, $this->tailOutput);

        // clean up
        $commander->run('fg', ['timeout_behavior' => "\x03"]);
    }

    public function testContinueCommandInBackground()
    {

    }
}

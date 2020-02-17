<?php /** @noinspection PhpUnhandledExceptionInspection */

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
            $connection->debug('terminating...');
            $connection->terminateCommand();
            // read the final output produced by the command when terminating
            $connection->getSSH2()->setTimeout(1);
            $connection->debug('reading tail...');
            $this->tailOutput = $connection->read();
        };

        $commander = $this->getSSHCommander($this->sshOptions);
        $command   = 'ping 127.0.0.1';

        // let the command run for 2 seconds
        $timeoutValue = 2;
        $commander->timeout(
            $timeoutValue,
            SSHConfig::TIMEOUT_CONDITION_RUNNING_TIMELIMIT,
            $behavior
        );

        $result = $commander->run($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());

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
            $this->tailOutput = $connection->read();
        };

        $commander = $this->getSSHCommander($this->sshOptions);
        $command   = 'ping 127.0.0.1';

        // let the command run for 2 seconds
        $timeoutValue = 2;

        $commander->timeout(
            $timeoutValue,
            SSHConfig::TIMEOUT_CONDITION_RUNNING_TIMELIMIT,
            $behavior
        );
        $commander->breakOnError(false);

        $result = $commander->run($command);

        $this->assertEquals($timeoutValue, (int)$result->getCommandElapsedTime());

        // We can assert that the command has been terminated by analyzing the
        // tail output after we sent CTRL+Z
        $this->assertStringContainsStringIgnoringCase('stopped', $this->tailOutput);
        $this->assertStringContainsString($command, $this->tailOutput);

        // clean up
        $result2 = $commander->run('fg', ['timeout_behavior' => "\x03"]);
        $this->assertEquals($timeoutValue, (int)$result2->getCommandElapsedTime());
    }

    public function testContinueCommandInBackground()
    {

    }
}

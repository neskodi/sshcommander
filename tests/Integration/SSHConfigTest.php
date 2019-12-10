<?php

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Tests\IntegrationTestCase;
use Neskodi\SSHCommander\SSHCommander;
use Neskodi\SSHCommander\SSHConfig;
use Psr\Log\LogLevel;

class SSHConfigTest extends IntegrationTestCase
{
    /** @noinspection PhpUnhandledExceptionInspection */
    public function testCommandTimeout()
    {
        $logger = $this->getTestLogger(LogLevel::INFO);

        $commander = new SSHCommander($this->sshOptions, $logger);

        $commander->run('ping google.com');

        var_dump(array_column($logger->popHandler()->getRecords(), 'message'));
    }
}

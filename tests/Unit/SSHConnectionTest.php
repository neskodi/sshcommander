<?php /** @noinspection PhpUndefinedMethodInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\Unit;

use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Tests\TestCase;
use Neskodi\SSHCommander\SSHConnection;
use Neskodi\SSHCommander\SSHCommand;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class SSHConnectionTest extends TestCase
{
    public function testConstructor()
    {
        $config = $this->getTestConfigAsObject();
        $logger = $this->getTestLogger(LogLevel::DEBUG);

        $connection = new SSHConnection($config, $logger);

        $this->assertInstanceOf(LoggerInterface::class, $connection->getLogger());
        $this->assertInstanceOf(SSHConfigInterface::class, $connection->getConfig());
    }

    public function testSetTimerPrecision(): void
    {
        $config = $this->getTestConfigAsObject();
        $config->set('autologin', false);
        $config->set('log_level', LogLevel::DEBUG);

        $connection = $this->getMockConnection();
        $precision  = 3;
        $connection->setTimerPrecision($precision);

        $connection->exec(new SSHCommand('ls', $config->all()));

        $handler = $connection->getLogger()->popHandler();
        $records = array_column($handler->getRecords(), 'message');

        $timerDecimals = false;
        $matches       = [];
        $regex         = '/Command completed in ([\d]+)\.([\d]+) seconds/i';

        foreach ($records as $record) {
            if (!preg_match($regex, $record, $matches)) {
                continue;
            }

            $timerDecimals = strlen($matches[2]);
        }

        $this->assertIsInt($timerDecimals);
        $this->assertSame($precision, $timerDecimals);
    }
}

<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\unit;

use Neskodi\SSHCommander\Factories\LoggerFactory;
use Neskodi\SSHCommander\Tests\TestCase;
use Neskodi\SSHCommander\SSHConfig;
use Neskodi\SSHCommander\Utils;
use Psr\Log\LoggerInterface;

class LoggerFactoryTest extends TestCase
{
    public function testMakeLoggerShouldFailWithMissingPath(): void
    {
        $config = $this->getTestConfigAsArray(self::CONFIG_CONNECTION_ONLY);

        $this->assertArrayNotHasKey('log_file', $config);

        $this->assertNull(LoggerFactory::makeLogger(new SSHConfig($config)));
    }


    public function testMakeLoggerShouldFailWithNonWritablePath(): void
    {
        $file    = '/no/such/file/2983149208p348-10';

        $config = $this->getTestConfigAsArray(self::CONFIG_FULL, [
            'log_file' => $file
        ]);

        $this->assertEquals($config['log_file'], $file);

        $this->assertNull(LoggerFactory::makeLogger(new SSHConfig($config)));
    }

    public function testMakeLoggerSuccessful(): void
    {
        $file = $_ENV['ssh_log_file'];

        $this->assertTrue(Utils::isWritableOrCreatable($file));

        $config = $this->getTestConfigAsArray(self::CONFIG_FULL, [
            'log_file' => $file
        ]);

        $this->assertEquals($config['log_file'], $file);

        $this->assertInstanceOf(
            LoggerInterface::class,
            LoggerFactory::makeLogger(new SSHConfig($config))
        );
    }
}

<?php /** @noinspection PhpUndefinedMethodInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\Unit;

use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Tests\Mocks\MockSSHConnection;
use Neskodi\SSHCommander\Tests\TestCase;
use Neskodi\SSHCommander\SSHConnection;
use Neskodi\SSHCommander\SSHCommand;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;
use phpseclib\Net\SSH2;
use Psr\Log\LogLevel;

class SSHConnectionTest extends TestCase
{
    const REGEX_AUTHENTICATING_WITH_PASSWORD = '/Authenticating as user "[^"]+" with a password/i';
    const REGEX_AUTHENTICATING_WITH_KEY      = '/Authenticating as user "[^"]+" with a private key/i';

    const AUTH_TYPE_PASSWORD          = 'password';
    const AUTH_TYPE_KEY               = 'key';
    const AUTH_TYPE_KEY_PROTECTED     = 'key-protected';
    const AUTH_TYPE_KEYFILE           = 'keyfile';
    const AUTH_TYPE_KEYFILE_PROTECTED = 'keyfile-protected';

    const AUTH_PASSWORD = 'secret';

    public function testConstructor(): void
    {
        $config = $this->getTestConfigAsObject();
        $logger = $this->getTestLogger(LogLevel::DEBUG);

        $connection = new SSHConnection($config, $logger);

        $this->assertInstanceOf(
            LoggerInterface::class,
            $connection->getLogger()
        );

        $this->assertInstanceOf(
            SSHConfigInterface::class,
            $connection->getConfig()
        );
    }

    public function testAutoLoginOff(): void
    {
        $config = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            ['autologin' => false]
        );

        $connection = new SSHConnection($config);

        $this->assertFalse($connection->isAuthenticated());
    }

    public function testAutoLoginWithPassword(): void
    {
        $this->AutoLoginAndCheckLogRecords(
            $this->getConnectionConfig(
                self::AUTH_TYPE_PASSWORD,
                self::AUTH_PASSWORD
            ),
            static::REGEX_AUTHENTICATING_WITH_PASSWORD
        );
    }

    public function testAutoLoginWithKey(): void
    {
        $this->AutoLoginAndCheckLogRecords(
            $this->getConnectionConfig(
                self::AUTH_TYPE_KEY,
                self::AUTH_PASSWORD
            ),
            static::REGEX_AUTHENTICATING_WITH_KEY
        );
    }

    public function testAutoLoginWithKeyfile(): void
    {
        $this->AutoLoginAndCheckLogRecords(
            $this->getConnectionConfig(
                self::AUTH_TYPE_KEYFILE,
                self::AUTH_PASSWORD
            ),
            static::REGEX_AUTHENTICATING_WITH_KEY
        );
    }

    public function testAutoLoginWithProtectedKey(): void
    {
        $this->AutoLoginAndCheckLogRecords(
            $this->getConnectionConfig(
                self::AUTH_TYPE_KEY_PROTECTED,
                self::AUTH_PASSWORD
            ),
            static::REGEX_AUTHENTICATING_WITH_KEY
        );
    }

    public function testAutoLoginWithProtectedKeyfile(): void
    {
        $this->AutoLoginAndCheckLogRecords(
            $this->getConnectionConfig(
                self::AUTH_TYPE_KEYFILE_PROTECTED,
                self::AUTH_PASSWORD
            ),
            static::REGEX_AUTHENTICATING_WITH_KEY
        );
    }

    public function testAutoLoginFailed(): void
    {
        $config = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            $this->getConnectionConfig(
                self::AUTH_TYPE_PASSWORD,
                self::AUTH_PASSWORD
            )
        );

        $this->expectException(AuthenticationException::class);

        MockSSHConnection::expect(MockSSHConnection::RESULT_ERROR);
        new MockSSHConnection($config);
    }

    public function testLazyLoginWithPassword(): void
    {
        $this->LazyLoginAndCheckLogRecords(
            $this->getConnectionConfig(
                self::AUTH_TYPE_PASSWORD,
                self::AUTH_PASSWORD,
                false
            ),
            static::REGEX_AUTHENTICATING_WITH_PASSWORD
        );
    }

    public function testLazyLoginWithKey(): void
    {
        $this->LazyLoginAndCheckLogRecords(
            $this->getConnectionConfig(
                self::AUTH_TYPE_KEY,
                self::AUTH_PASSWORD,
                false
            ),
            static::REGEX_AUTHENTICATING_WITH_KEY
        );
    }

    public function testLazyLoginWithKeyfile(): void
    {
        $this->LazyLoginAndCheckLogRecords(
            $this->getConnectionConfig(
                self::AUTH_TYPE_KEYFILE,
                self::AUTH_PASSWORD,
                false
            ),
            static::REGEX_AUTHENTICATING_WITH_KEY
        );
    }

    public function testLazyLoginWithProtectedKey(): void
    {
        $this->LazyLoginAndCheckLogRecords(
            $this->getConnectionConfig(
                self::AUTH_TYPE_KEY_PROTECTED,
                self::AUTH_PASSWORD,
                false
            ),
            static::REGEX_AUTHENTICATING_WITH_KEY
        );
    }

    public function testLazyLoginWithProtectedKeyfile(): void
    {
        $this->LazyLoginAndCheckLogRecords(
            $this->getConnectionConfig(
                self::AUTH_TYPE_KEYFILE_PROTECTED,
                self::AUTH_PASSWORD,
                false
            ),
            static::REGEX_AUTHENTICATING_WITH_KEY
        );
    }

    public function testLazyLoginFailed(): void
    {
        $config = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            $this->getConnectionConfig(
                self::AUTH_TYPE_PASSWORD,
                self::AUTH_PASSWORD,
                false
            )
        );

        $this->expectException(AuthenticationException::class);

        MockSSHConnection::expect(MockSSHConnection::RESULT_ERROR);
        $connection = new MockSSHConnection($config);

        $this->assertFalse($connection->isAuthenticated());

        $connection->exec(new SSHCommand('ls', $config));
    }

    public function testAuthenticateOnlyWithPassword(): void
    {
        $this->AuthOnlyAndCheckLogRecords(
            $this->getConnectionConfig(
                self::AUTH_TYPE_PASSWORD,
                self::AUTH_PASSWORD,
                false
            )
        );
    }

    public function testAuthenticateOnlyWithKey(): void
    {
        $this->AuthOnlyAndCheckLogRecords(
            $this->getConnectionConfig(
                self::AUTH_TYPE_KEY,
                self::AUTH_PASSWORD,
                false
            )
        );
    }

    public function testAuthenticateOnlyWithKeyfile(): void
    {
        $this->AuthOnlyAndCheckLogRecords(
            $this->getConnectionConfig(
                self::AUTH_TYPE_KEYFILE,
                self::AUTH_PASSWORD,
                false
            )
        );
    }

    public function testAuthenticateOnlyWithProtectedKey(): void
    {
        $this->AuthOnlyAndCheckLogRecords(
            $this->getConnectionConfig(
                self::AUTH_TYPE_KEY_PROTECTED,
                self::AUTH_PASSWORD,
                false
            )
        );
    }

    public function testAuthenticateOnlyWithProtectedKeyfile(): void
    {
        $this->AuthOnlyAndCheckLogRecords(
            $this->getConnectionConfig(
                self::AUTH_TYPE_KEYFILE_PROTECTED,
                self::AUTH_PASSWORD,
                false
            )
        );
    }

    public function testAuthenticateOnlyFailed(): void
    {
        $config = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            $this->getConnectionConfig(
                self::AUTH_TYPE_PASSWORD,
                self::AUTH_PASSWORD,
                false
            )
        );

        $this->expectException(AuthenticationException::class);

        MockSSHConnection::expect(MockSSHConnection::RESULT_ERROR);
        $connection = new MockSSHConnection($config);

        $this->assertFalse($connection->isAuthenticated());

        $connection->authenticate();
    }

    public function testExec(): void
    {
        $config = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            $this->getConnectionConfig(
                self::AUTH_TYPE_PASSWORD,
                self::AUTH_PASSWORD
            )
        );

        MockSSHConnection::expect(MockSSHConnection::RESULT_SUCCESS);
        $connection = new MockSSHConnection($config);

        $this->assertTrue($connection->isAuthenticated());
        $this->assertNull($connection->getLastExitCode());

        $connection->exec(new SSHCommand('ls', $config));

        $this->assertIsInt($connection->getLastExitCode());
    }

    public function testResetOutput(): void
    {
        $config = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            $this->getConnectionConfig(
                self::AUTH_TYPE_PASSWORD,
                self::AUTH_PASSWORD
            )
        );
        $config->set('separate_stderr', true);

        MockSSHConnection::expect(MockSSHConnection::RESULT_SUCCESS);
        $connection = new MockSSHConnection($config);
        $this->assertTrue($connection->isAuthenticated());

        // we need stdout to be populated too
        MockSSHConnection::expect(MockSSHConnection::RESULT_ERROR);
        $connection->exec(new SSHCommand('ls', $config));

        $this->assertIsInt($connection->getLastExitCode());
        $this->assertNotEmpty($connection->getStdOutLines());
        $this->assertNotEmpty($connection->getStdErrLines());

        $connection->resetOutput();

        $this->assertNull($connection->getLastExitCode());
        $this->assertEmpty($connection->getStdOutLines());
        $this->assertEmpty($connection->getStdErrLines());
    }

    public function testSetTimeout(): void
    {
        $timeout = 150;

        $config     = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            ['autologin' => false]
        );
        $connection = new SSHConnection($config);

        $connection->setTimeout($timeout);

        $this->assertSame($timeout, $connection->getSSH2()->timeout);
    }

    public function testResetTimeout(): void
    {
        $default = SSHConnection::DEFAULT_TIMEOUT;
        $timeout = 150;

        $config     = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            ['autologin' => false]
        );
        $connection = new SSHConnection($config);

        $connection->setTimeout($timeout);

        $this->assertSame($timeout, $connection->getSSH2()->timeout);

        $connection->resetTimeout();

        $this->assertSame($default, $connection->getSSH2()->timeout);
    }

    public function testGetSSH2(): void
    {
        $config     = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            ['autologin' => false]
        );
        $connection = new SSHConnection($config);

        $this->assertInstanceOf(SSH2::class, $connection->getSSH2());
    }

    public function testSetTimerPrecision(): void
    {
        $config = $this->getTestConfigAsObject();
        $config->set('autologin', false);
        $config->set('log_level', LogLevel::DEBUG);

        $connection = $this->getMockConnection();
        MockSSHConnection::expect(MockSSHConnection::RESULT_SUCCESS);
        $precision = 3;
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
        $this->assertLessThanOrEqual($precision, $timerDecimals);
    }

    protected function AutoLoginAndCheckLogRecords(array $config, string $regex)
    {
        $config = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            $config
        );

        $logger = $this->getTestLogger(LogLevel::DEBUG);

        MockSSHConnection::expect(MockSSHConnection::RESULT_SUCCESS);
        $connection = new MockSSHConnection($config, $logger);

        $this->assertTrue($connection->isAuthenticated());

        /** @var TestHandler $handler */
        $handler = $connection->getLogger()->popHandler();

        $this->assertTrue($handler->hasRecordThatMatches(
            $regex,
            LogLevel::INFO
        ));
    }

    protected function LazyLoginAndCheckLogRecords(array $config, string $regex)
    {
        $config = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            $config
        );

        $logger = $this->getTestLogger(LogLevel::DEBUG);

        $connection = new MockSSHConnection($config, $logger);

        $this->assertFalse($connection->isAuthenticated());

        MockSSHConnection::expect(MockSSHConnection::RESULT_SUCCESS);
        $connection->exec(new SSHCommand('ls', $config));

        /** @var TestHandler $handler */
        $handler = $connection->getLogger()->popHandler();

        $this->assertTrue($handler->hasRecordThatMatches(
            $regex,
            LogLevel::INFO
        ));
    }

    protected function AuthOnlyAndCheckLogRecords(array $config): void
    {
        $config = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            $config
        );

        $logger = $this->getTestLogger(LogLevel::DEBUG);

        $connection = new MockSSHConnection($config, $logger);

        $this->assertFalse($connection->isAuthenticated());

        MockSSHConnection::expect(MockSSHConnection::RESULT_SUCCESS);
        $connection->authenticate();

        $this->assertTrue($connection->isAuthenticated());

        /** @var TestHandler $handler */
        $handler = $connection->getLogger()->popHandler();

        $this->assertTrue($handler->hasRecord(
            'Authenticated.',
            LogLevel::INFO
        ));
    }
}

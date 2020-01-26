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
        $logger = $this->createTestLogger();

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
            $this->getMockConnectionConfigWithType(TestCase::AUTH_TYPE_PASSWORD),
            static::REGEX_AUTHENTICATING_WITH_PASSWORD
        );
    }

    public function testAutoLoginWithKey(): void
    {
        $this->AutoLoginAndCheckLogRecords(
            $this->getMockConnectionConfigWithType(TestCase::AUTH_TYPE_KEY),
            static::REGEX_AUTHENTICATING_WITH_KEY
        );
    }

    public function testAutoLoginWithKeyfile(): void
    {
        $this->AutoLoginAndCheckLogRecords(
            $this->getMockConnectionConfigWithType(TestCase::AUTH_TYPE_KEYFILE),
            static::REGEX_AUTHENTICATING_WITH_KEY
        );
    }

    public function testAutoLoginWithProtectedKey(): void
    {
        $this->AutoLoginAndCheckLogRecords(
            $this->getMockConnectionConfigWithType(
                TestCase::AUTH_TYPE_KEY_PROTECTED
            ),
            static::REGEX_AUTHENTICATING_WITH_KEY
        );
    }

    public function testAutoLoginWithProtectedKeyfile(): void
    {
        $this->AutoLoginAndCheckLogRecords(
            $this->getMockConnectionConfigWithType(
                TestCase::AUTH_TYPE_KEYFILE_PROTECTED
            ),
            static::REGEX_AUTHENTICATING_WITH_KEY
        );
    }

    public function testAutoLoginFailed(): void
    {
        $config = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            $this->getMockConnectionConfigWithType(TestCase::AUTH_TYPE_PASSWORD)
        );

        $this->expectException(AuthenticationException::class);

        MockSSHConnection::expect(MockSSHConnection::RESULT_ERROR);
        new MockSSHConnection($config);
    }

    public function testLazyLoginWithPassword(): void
    {
        $this->LazyLoginAndCheckLogRecords(
            $this->getMockConnectionConfigWithType(
                TestCase::AUTH_TYPE_PASSWORD,
                false
            ),
            static::REGEX_AUTHENTICATING_WITH_PASSWORD
        );
    }

    public function testLazyLoginWithKey(): void
    {
        $this->LazyLoginAndCheckLogRecords(
            $this->getMockConnectionConfigWithType(TestCase::AUTH_TYPE_KEY, false),
            static::REGEX_AUTHENTICATING_WITH_KEY
        );
    }

    public function testLazyLoginWithKeyfile(): void
    {
        $this->LazyLoginAndCheckLogRecords(
            $this->getMockConnectionConfigWithType(
                TestCase::AUTH_TYPE_KEYFILE,
                false
            ),
            static::REGEX_AUTHENTICATING_WITH_KEY
        );
    }

    public function testLazyLoginWithProtectedKey(): void
    {
        $this->LazyLoginAndCheckLogRecords(
            $this->getMockConnectionConfigWithType(
                TestCase::AUTH_TYPE_KEY_PROTECTED,
                false
            ),
            static::REGEX_AUTHENTICATING_WITH_KEY
        );
    }

    public function testLazyLoginWithProtectedKeyfile(): void
    {
        $this->LazyLoginAndCheckLogRecords(
            $this->getMockConnectionConfigWithType(
                TestCase::AUTH_TYPE_KEYFILE_PROTECTED,
                false
            ),
            static::REGEX_AUTHENTICATING_WITH_KEY
        );
    }

    public function testLazyLoginFailed(): void
    {
        $config = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            $this->getMockConnectionConfigWithType(
                TestCase::AUTH_TYPE_PASSWORD,
                false
            )
        );

        $this->expectException(AuthenticationException::class);

        MockSSHConnection::expect(MockSSHConnection::RESULT_ERROR);
        $connection = new MockSSHConnection($config);

        $this->assertFalse($connection->isAuthenticated());

        $connection->execIsolated(new SSHCommand('ls', $config));
    }

    public function testAuthenticateOnlyWithPassword(): void
    {
        $this->AuthOnlyAndCheckLogRecords(
            $this->getMockConnectionConfigWithType(
                TestCase::AUTH_TYPE_PASSWORD,
                false
            )
        );
    }

    public function testAuthenticateOnlyWithKey(): void
    {
        $this->AuthOnlyAndCheckLogRecords(
            $this->getMockConnectionConfigWithType(
                TestCase::AUTH_TYPE_KEY,
                false
            )
        );
    }

    public function testAuthenticateOnlyWithKeyfile(): void
    {
        $this->AuthOnlyAndCheckLogRecords(
            $this->getMockConnectionConfigWithType(
                TestCase::AUTH_TYPE_KEYFILE,
                false
            )
        );
    }

    public function testAuthenticateOnlyWithProtectedKey(): void
    {
        $this->AuthOnlyAndCheckLogRecords(
            $this->getMockConnectionConfigWithType(
                TestCase::AUTH_TYPE_KEY_PROTECTED,
                false
            )
        );
    }

    public function testAuthenticateOnlyWithProtectedKeyfile(): void
    {
        $this->AuthOnlyAndCheckLogRecords(
            $this->getMockConnectionConfigWithType(
                TestCase::AUTH_TYPE_KEYFILE_PROTECTED,
                false
            )
        );
    }

    public function testAuthenticateOnlyFailed(): void
    {
        $config = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            $this->getMockConnectionConfigWithType(
                TestCase::AUTH_TYPE_PASSWORD,
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
            $this->getMockConnectionConfigWithType(TestCase::AUTH_TYPE_PASSWORD)
        );

        MockSSHConnection::expect(MockSSHConnection::RESULT_SUCCESS);
        $connection = new MockSSHConnection($config);

        $this->assertTrue($connection->isAuthenticated());
        $this->assertNull($connection->getLastExitCode());

        $connection->execIsolated(new SSHCommand('ls', $config));

        $this->assertIsInt($connection->getLastExitCode());
    }

    public function testresetResults(): void
    {
        $config = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            $this->getMockConnectionConfigWithType(TestCase::AUTH_TYPE_PASSWORD)
        );
        $config->set('separate_stderr', true);

        MockSSHConnection::expect(MockSSHConnection::RESULT_SUCCESS);
        $connection = new MockSSHConnection($config);
        $this->assertTrue($connection->isAuthenticated());

        // we need stdout to be populated too
        MockSSHConnection::expect(MockSSHConnection::RESULT_ERROR);
        $connection->execIsolated(new SSHCommand('ls', $config));

        $this->assertIsInt($connection->getLastExitCode());
        $this->assertNotEmpty($connection->getStdOutLines());
        $this->assertNotEmpty($connection->getStdErrLines());

        $connection->resetResults();

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
        $timeout = 150;

        $config     = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            ['autologin' => false]
        );
        $default = $config->getDefault('timeout_command');

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

    protected function AutoLoginAndCheckLogRecords(array $config, string $regex)
    {
        $config = $this->getTestConfigAsObject(
            self::CONFIG_FULL,
            $config
        );

        $logger = $this->createTestLogger();

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

        $logger = $this->createTestLogger();

        $connection = new MockSSHConnection($config, $logger);

        $this->assertFalse($connection->isAuthenticated());

        MockSSHConnection::expect(MockSSHConnection::RESULT_SUCCESS);
        $connection->execIsolated(new SSHCommand('ls', $config));

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

        $logger = $this->createTestLogger();

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

<?php /** @noinspection DuplicatedCode */
/** @noinspection PhpIncludeInspection */
/** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Tests\IntegrationTestCase;
use Neskodi\SSHCommander\SSHConfig;
use RuntimeException;

class SSHCommanderTest extends IntegrationTestCase
{
    public function testFailedConnectionIsProperlyReported(): void
    {
        $this->expectException(AuthenticationException::class);

        $commander = $this->getSSHCommander([
            'host'            => 'example.com',
            'user'            => '*',
            'password'        => '*',
            'timeout_connect' => 2,
        ]);

        $commander->run('pwd');
    }

    public function testFailedAuthenticationIsProperlyReported(): void
    {
        $this->expectException(AuthenticationException::class);

        $options = array_merge($this->sshOptions, [
            'user' => '****',
        ]);

        $commander = $this->getSSHCommander($options);
        $commander->run('pwd');
    }

    public function testFailedCommandIsReflectedInResult(): void
    {
        $config = $this->sshOptions;
        $config['break_on_error'] = false;

        $commander = $this->getSSHCommander($config);

        $result    = $commander->run('cd /no/such/dir');

        $this->assertTrue($result->isError());
        $this->assertStringContainsStringIgnoringCase('no such', $result);
    }

    public function testIsolatedCommand(): void
    {
        $commander = $this->getSSHCommander($this->sshOptions);

        $result = $commander->runIsolated('echo AAA');

        $this->assertSame('AAA', (string)$result);
        $this->assertTrue($result->isOk());
    }

    public function testInteractiveCommand(): void
    {
        $commander = $this->getSSHCommander($this->sshOptions);

        $commander->run('cd /tmp');
        $result = $commander->run('pwd');

        $this->assertSame('/tmp', (string)$result);
        $this->assertTrue($result->isOk());
    }

    public function testConsecutiveIsolatedCommands(): void
    {
        $commander = $this->getSSHCommander($this->sshOptions);

        $resultA = $commander->runIsolated('echo AAA');
        $resultB = $commander->runIsolated('echo BBB');
        $resultC = $commander->runIsolated('echo CCC');

        $this->assertSame('AAA', (string)$resultA);
        $this->assertTrue($resultA->isOk());
        $this->assertSame('BBB', (string)$resultB);
        $this->assertTrue($resultB->isOk());
        $this->assertSame('CCC', (string)$resultC);
        $this->assertTrue($resultC->isOk());
    }

    public function testConsecutiveInteractiveCommands(): void
    {
        $commander = $this->getSSHCommander($this->sshOptions);

        $resultA = $commander->run('echo AAA');
        $resultB = $commander->run('echo BBB');
        $resultC = $commander->run('echo CCC');

        $this->assertSame('AAA', (string)$resultA);
        $this->assertTrue($resultA->isOk());
        $this->assertSame('BBB', (string)$resultB);
        $this->assertTrue($resultB->isOk());
        $this->assertSame('CCC', (string)$resultC);
        $this->assertTrue($resultC->isOk());
    }

    public function testMultiCommandCanBeProvidedAsString(): void
    {
        $commander = $this->getSSHCommander($this->sshOptions);

        $basedir = '/tmp';

        $result = $commander->run("cd $basedir" . "\n" . 'pwd');

        $this->assertSame($basedir, (string)$result);
    }

    public function testMultiCommandCanBeProvidedAsArray(): void
    {
        $commander = $this->getSSHCommander($this->sshOptions);

        $basedir = '/tmp';

        $result = $commander->run(["cd $basedir", 'pwd']);

        $this->assertSame($basedir, (string)$result);
    }

    public function testLoginWithPublicKeyWorks(): void
    {
        try {
            $this->requireKeyfile();
            $this->requireUser();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $options = [
            'host' => $this->sshOptions['host'],
            'port' => $this->sshOptions['port'] ?? 22,
            'user' => $this->sshOptions['user'],
            'key'  => file_get_contents($this->sshOptions['keyfile']),

            'autologin' => true,
        ];

        $commander = $this->getSSHCommander($options);

        $connection = $commander->getConnection();

        $this->assertTrue($connection->isAuthenticated());
    }

    public function testLoginWithPublicKeyfileWorks(): void
    {
        try {
            $this->requireKeyfile();
            $this->requireUser();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $options = [
            'host'    => $this->sshOptions['host'],
            'port'    => $this->sshOptions['port'] ?? 22,
            'user'    => $this->sshOptions['user'],
            'keyfile' => $this->sshOptions['keyfile'],

            'autologin' => true,
        ];

        $commander = $this->getSSHCommander($options);

        $connection = $commander->getConnection();

        $this->assertTrue($connection->isAuthenticated());
    }

    public function testLoginWithProtectedKeyfileWorks(): void
    {
        try {
            $this->requireUser();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $options = [
            'host'     => $this->sshOptions['host'],
            'port'     => $this->sshOptions['port'] ?? 22,
            'user'     => $this->sshOptions['user'],
            'keyfile'  => $this->getProtectedPrivateKeyFile(),
            'password' => 'secret',

            'autologin' => true,
        ];

        $commander = $this->getSSHCommander($options);

        $connection = $commander->getConnection();

        $this->assertTrue($connection->isAuthenticated());
    }

    public function testLoginWithPasswordWorks(): void
    {
        try {
            $this->requirePassword();
            $this->requireUser();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $options = [
            'host'     => $this->sshOptions['host'],
            'port'     => $this->sshOptions['port'] ?? 22,
            'user'     => $this->sshOptions['user'],
            'password' => $this->sshOptions['password'],

            'autologin' => true,
        ];

        $commander = $this->getSSHCommander($options);

        $connection = $commander->getConnection();

        $this->assertTrue($connection->isAuthenticated());
    }

    public function testLazyAuthentication(): void
    {
        $config = new SSHConfig($this->sshOptions);
        $config->set('autologin', false);

        $commander = $this->getSSHCommander($config);

        $this->assertFalse($commander->getConnection()->isAuthenticated());

        $commander->run('ls');

        $this->assertTrue($commander->getConnection()->isAuthenticated());
    }
}

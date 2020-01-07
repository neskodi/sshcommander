<?php /** @noinspection PhpIncludeInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\Tests\IntegrationTestCase;
use Neskodi\SSHCommander\SSHCommander;
use Neskodi\SSHCommander\SSHConfig;
use RuntimeException;

class SSHCommanderTest extends IntegrationTestCase
{
    public function testFailedConnectionIsProperlyReported(): void
    {
        $this->expectException(AuthenticationException::class);

        $commander = new SSHCommander([
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

        $commander = new SSHCommander($options);
        $commander->run('pwd');
    }

    public function testFailedCommandIsProperlyReported(): void
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $commander = new SSHCommander($this->sshOptions);
        $result    = $commander->run('cd /no!/such!/directory!');

        $this->assertTrue($result->isError());
        $this->assertStringContainsStringIgnoringCase('no such', $result);
    }

    public function testIsolatedCommandRun(): void
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $commander = new SSHCommander($this->sshOptions);

        $result = $commander->runIsolated('echo AAA');

        $this->assertSame('AAA', (string)$result);
        $this->assertTrue($result->isOk());
    }

    public function testInteractiveCommandRun(): void
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $commander = new SSHCommander($this->sshOptions);

        $commander->run('cd /tmp');
        $result = $commander->run('pwd');

        $this->assertSame('/tmp', (string)$result);
        $this->assertTrue($result->isOk());
    }

    public function testMultiCommandCanBeProvidedAsString(): void
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $commander = new SSHCommander($this->sshOptions);

        $basedir = '/tmp';

        $result = $commander->run("cd $basedir" . "\n" . 'pwd');

        $this->assertSame($basedir, (string)$result);
    }

    public function testMultiCommandCanBeProvidedAsArray(): void
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $commander = new SSHCommander($this->sshOptions);

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

        $commander = new SSHCommander($options);

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

        $commander = new SSHCommander($options);

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

        $commander = new SSHCommander($options);

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

        $commander = new SSHCommander($options);

        $connection = $commander->getConnection();

        $this->assertTrue($connection->isAuthenticated());
    }

    public function testLazyAuthentication(): void
    {
        $config = new SSHConfig($this->sshOptions);
        $config->set('autologin', false);

        $commander = new SSHCommander($config);

        $this->assertFalse($commander->getConnection()->isAuthenticated());

        $commander->run('ls');

        $this->assertTrue($commander->getConnection()->isAuthenticated());
    }
}

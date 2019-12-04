<?php /** @noinspection ALL */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Exceptions\AuthenticationException;
use Neskodi\SSHCommander\SSHConfig;
use Neskodi\SSHCommander\Tests\TestCase;
use Neskodi\SSHCommander\SSHCommander;
use RuntimeException;

class SSHCommanderTest extends TestCase
{
    protected function setUp(): void
    {
        $this->buildSshOptions();

        if (empty($this->sshOptions)) {
            // we can't test anything without a working connection
            $this->markTestSkipped(
                'SSHCommander needs a working SSH connection ' .
                'to run integration tests. Please set the connection ' .
                'information in phpunit.xml.');
        }
    }

    public function testFailedConnectionIsProperlyReported()
    {
        $this->expectException(AuthenticationException::class);

        $commander = new SSHCommander([
            'host'            => 'example.com',
            'user'            => '*',
            'timeout_connect' => 2,
        ]);
        $commander->run('pwd');
    }

    public function testFailedAuthenticationIsProperlyReported()
    {
        $this->expectException(AuthenticationException::class);

        $options = array_merge($this->sshOptions, [
            'user' => '****',
        ]);

        $commander = new SSHCommander($options);
        $commander->run('pwd');
    }

    public function testFailedCommandIsProperlyReported()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            return $this->markTestSkipped($e->getMessage());
        }

        $commander = new SSHCommander($this->sshOptions);
        $result    = $commander->run('cd /no!/such!/directory!');

        $this->assertTrue($result->isError());
        $this->assertStringContainsStringIgnoringCase('no such', $result);
    }

    public function testCommandCanBeRunSuccessfully()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            return $this->markTestSkipped($e->getMessage());
        }

        $basedir = '/tmp';

        $options = array_merge($this->sshOptions, [
            'basedir' => $basedir,
        ]);

        $commander = new SSHCommander($options);

        $result = $commander->run('pwd');

        $this->assertSame($basedir, (string)$result);
    }

    public function testLoginWithPublicKeyWorks()
    {
        try {
            $this->requireKeyfile();
            $this->requireUser();
        } catch (RuntimeException $e) {
            return $this->markTestSkipped($e->getMessage());
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

    public function testLoginWithPublicKeyfileWorks()
    {
        try {
            $this->requireKeyfile();
            $this->requireUser();
        } catch (RuntimeException $e) {
            return $this->markTestSkipped($e->getMessage());
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

    public function testLoginWithPasswordWorks()
    {
        try {
            $this->requirePassword();
            $this->requireUser();
        } catch (RuntimeException $e) {
            return $this->markTestSkipped($e->getMessage());
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

    public function testDefaultConfigurationIsUsedByDefault()
    {
        $defaultConfig = (array)include(SSHConfig::getDefaultConfigFileLocation());
        $extra         = ['host' => '127.0.0.1'];
        $defaultConfig = array_merge($defaultConfig, $extra);

        $commander = new SSHCommander(['host' => '127.0.0.1']);

        $resultConfig = $commander->getConfig()->all();

        $this->assertEquals($defaultConfig, $resultConfig);
    }

    public function testUserCanSetConfigurationAsFile()
    {
        SSHCommander::setConfigFile($this->getTestConfigFile());
        $extra      = [
            'host' => '********',
            'user' => '********',
        ];
        $testConfig = $this->getTestConfigAsArray();
        $testConfig = array_merge($testConfig, $extra);

        $commander = new SSHCommander($extra);

        $resultConfig = $commander->getConfig()->all();
        $this->assertEquals($testConfig, $resultConfig);

        // reset for further tests
        SSHConfig::resetConfigFileLocation();
    }

    public function testUserCanSetConfigurationAsArgument()
    {
        $testConfig = $this->getTestConfigAsArray();
        $extra      = [
            'host' => '********',
            'user' => '********',
        ];
        $testConfig = array_merge($testConfig, $extra);

        $commander = new SSHCommander($testConfig);

        $resultConfig = $commander->getConfig()->all();

        $this->assertEquals($testConfig, $resultConfig);
    }

}

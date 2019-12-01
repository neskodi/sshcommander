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
    /**
     * @var array
     */
    protected $sshOptions = [];

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

    protected function buildSshOptions()
    {
        if (!isset($_ENV['ssh_host']) || empty($_ENV['ssh_host'])) {
            return;
        }

        $target           = explode(':', $_ENV['ssh_host']);
        $this->sshOptions = ['host' => $target[0]];
        if (count($target) > 1) {
            $this->sshOptions['port'] = (int)$target[1];
        }

        foreach (['ssh_user', 'ssh_keyfile', 'ssh_password'] as $op) {
            if (isset($_ENV[$op])) {
                $this->sshOptions[substr($op, 4)] = $_ENV[$op];
            }
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

    protected function requireKeyfile()
    {
        if (
            !isset($this->sshOptions['keyfile']) ||
            empty($this->sshOptions['keyfile'])
        ) {
            throw new RuntimeException(
                'Cannot test login with public key: ' .
                'no public key is set in phpunit xml configuration'
            );
        }
    }

    protected function requirePassword()
    {
        if (
            !isset($this->sshOptions['password']) ||
            empty($this->sshOptions['password'])
        ) {
            throw new RuntimeException(
                'Cannot test login with password: ' .
                'no password is set in phpunit xml configuration'
            );
        }
    }

    protected function requireUser()
    {
        if (
            !isset($this->sshOptions['user']) ||
            empty($this->sshOptions['user'])
        ) {
            throw new RuntimeException(
                'Cannot test login: ssh username is not set ' .
                'in phpunit xml configuration'
            );
        }
    }

    protected function requireAuthCredential()
    {
        // require either keyfile or password
        $passwordMissing = (
            !isset($this->sshOptions['password']) ||
            empty($this->sshOptions['password'])
        );

        $keyfileMissing = (
            !isset($this->sshOptions['keyfile']) ||
            empty($this->sshOptions['keyfile'])
        );

        if ($passwordMissing && $keyfileMissing) {
            throw new RuntimeException(
                'Cannot run tests: either keyfile or password must be set ' .
                'in phpunit xml configuration'
            );
        }
    }
}

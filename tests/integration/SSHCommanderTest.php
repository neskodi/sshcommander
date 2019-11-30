<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Integration;

use Neskodi\SSHCommander\SSHCommander;
use PHPUnit\Framework\TestCase;

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

        $target = explode(':', $_ENV['ssh_host']);
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

    }

    public function testFailedAuthenticationIsProperlyReported()
    {

    }

    public function testFailedCommandIsProperlyReported()
    {

    }

    public function testCommandCanBeRunSuccessfully()
    {
        $basedir = '/var/www/vhosts';

        $options = array_merge($this->sshOptions, [
            'basedir' => $basedir,
        ]);

        $host = new SSHCommander($options);

        $result = $host->run('pwd');

        $this->assertSame('/var/www/vhosts', (string)$result);
    }

    public function testLoginWithPublicKeyWorks()
    {

    }

    public function testLoginWithPublicKeyfileWorks()
    {

    }

    public function testLoginWithPasswordWorks()
    {

    }

    public function testCommandFailed()
    {

    }

    public function testDefaultConfigurationIsUsedByDefault()
    {

    }

    public function testUserCanSetConfigurationAsFile()
    {

    }

    public function testUserCanSetConfigurationAsArgument()
    {

    }
}

<?php /** @noinspection PhpIncludeInspection */

namespace Neskodi\SSHCommander\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Neskodi\SSHCommander\Factories\LoggerFactory;
use Monolog\Processor\PsrLogMessageProcessor;
use Neskodi\SSHCommander\SSHConfig;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Monolog\Logger;
use Exception;

class TestCase extends PHPUnitTestCase
{
    const CONNECTION_CONFIG_KEYS = [
        'host',
        'port',
        'user',
        'password',
        'key',
        'keyfile'
    ];

    const CONFIG_FULL            = '*';
    const CONFIG_CONNECTION_ONLY = 'connection';
    const CONFIG_SECONDARY_ONLY  = 'other';

    /**
     * @var array
     */
    protected $sshOptions = [];

    public function getTestConfigFile()
    {
        return dirname(__FILE__) . DIRECTORY_SEPARATOR . 'testconfig.php';
    }

    public function getTestConfigAsArray(
        $type = self::CONFIG_FULL,
        array $override = []
    ) {
        $testConfigFile = $this->getTestConfigFile();
        $values = (array)include($testConfigFile);

        switch ($type) {
            case self::CONFIG_CONNECTION_ONLY:
                $values = array_intersect_key(
                    $values,
                    array_flip(static::CONNECTION_CONFIG_KEYS)
                );
                break;
            case self::CONFIG_SECONDARY_ONLY:
                $values = array_diff_key(
                    $values,
                    array_flip(static::CONNECTION_CONFIG_KEYS)
                );
                break;
        }

        if ($override) {
            $values = array_merge($values, $override);
        }

        return $values;
    }

    public function getTestConfigAsObject(
        $type = self::CONFIG_FULL,
        array $override = []
    ) {
        return new SSHConfig($this->getTestConfigAsArray($type, $override));
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

    /**
     * Get an instance of logger with specific logging level.
     *
     * @param string $level
     *
     * @return LoggerInterface
     * @throws Exception
     */
    protected function getTestLogger(string $level): LoggerInterface
    {
        $logger = new Logger('test-ssh-commander-log');
        $logger->pushProcessor(new PsrLogMessageProcessor);

        $handler = new TestHandler($level);
        $handler->setFormatter(
            LoggerFactory::getStreamLineFormatter(
                $this->getTestConfigAsObject()
            )
        );
        $logger->pushHandler($handler);

        return $logger;
    }
}

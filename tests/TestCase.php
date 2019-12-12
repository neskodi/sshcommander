<?php /** @noinspection PhpUnhandledExceptionInspection */

/** @noinspection PhpIncludeInspection */

namespace Neskodi\SSHCommander\Tests;

use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Tests\Mocks\MockSSHConnection;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Neskodi\SSHCommander\Factories\LoggerFactory;
use Monolog\Processor\PsrLogMessageProcessor;
use Neskodi\SSHCommander\SSHConfig;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Monolog\Logger;
use Exception;

class TestCase extends PHPUnitTestCase
{
    const AUTH_TYPE_PASSWORD          = 'password';
    const AUTH_TYPE_KEY               = 'key';
    const AUTH_TYPE_KEY_PROTECTED     = 'key-protected';
    const AUTH_TYPE_KEYFILE           = 'keyfile';
    const AUTH_TYPE_KEYFILE_PROTECTED = 'keyfile-protected';

    const CONNECTION_CONFIG_KEYS = [
        'host',
        'port',
        'user',
        'password',
        'key',
        'keyfile',
    ];

    const CONFIG_FULL            = '*';
    const CONFIG_CONNECTION_ONLY = 'connection';
    const CONFIG_SECONDARY_ONLY  = 'other';

    /**
     * @var array
     */
    protected $sshOptions = [];

    /**
     * @var string
     */
    protected static $keyfilePassword;

    public function getTestConfigFile()
    {
        return dirname(__FILE__) . DIRECTORY_SEPARATOR . 'testconfig.php';
    }

    public function getTestConfigAsArray(
        $type = self::CONFIG_FULL,
        array $override = []
    ) {
        $testConfigFile = $this->getTestConfigFile();
        $values         = (array)include($testConfigFile);

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

    protected function getMockConnection(
        ?SSHConfigInterface $config = null
    ): SSHConnectionInterface {
        $config = $config ?? $this->getTestConfigAsObject();
        $logger = $this->getTestLogger(LogLevel::DEBUG);

        return new MockSSHConnection($config, $logger);
    }

    protected function getUnprotectedPrivateKeyFile()
    {
        return static::getKeyPath('testkey');
    }

    protected function getUnprotectedPrivateKeyContents()
    {
        return file_get_contents($this->getUnprotectedPrivateKeyFile());
    }

    protected function getProtectedPrivateKeyFile()
    {
        return static::getKeyPath('testkey-protected');
    }

    protected function getPasswordForProtectedKeyFile()
    {
        if (null === self::$keyfilePassword) {
            self::$keyfilePassword = include(
                static::getKeyPath('password.php')
            );
        }

        return self::$keyfilePassword;
    }

    protected function getProtectedPrivateKeyContents()
    {
        return file_get_contents($this->getProtectedPrivateKeyFile());
    }

    public static function getKeyPath(?string $file = null): string
    {
        $dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Keys';

        return $file
            ? $dir . DIRECTORY_SEPARATOR . $file
            : $dir;
    }

    protected function getMockConnectionConfigByType(
        string $type,
        bool $autologin = true
    ): array {
        switch ($type) {
            case static::AUTH_TYPE_PASSWORD:
                return [
                    'autologin' => $autologin,
                    'password'  => $this->getPasswordForProtectedKeyFile(),
                    'key'       => null,
                    'keyfile'   => null,
                ];
            case static::AUTH_TYPE_KEY:
                return [
                    'autologin' => $autologin,
                    'password'  => null,
                    'key'       => $this->getUnprotectedPrivateKeyContents(),
                    'keyfile'   => null,
                ];
            case static::AUTH_TYPE_KEY_PROTECTED:
                return [
                    'autologin' => $autologin,
                    'password'  => $this->getPasswordForProtectedKeyFile(),
                    'key'       => $this->getProtectedPrivateKeyContents(),
                    'keyfile'   => null,
                ];
            case static::AUTH_TYPE_KEYFILE:
                return [
                    'autologin' => $autologin,
                    'password'  => null,
                    'key'       => null,
                    'keyfile'   => $this->getProtectedPrivateKeyFile(),
                ];
            case static::AUTH_TYPE_KEYFILE_PROTECTED:
                return [
                    'autologin' => $autologin,
                    'password'  => $this->getPasswordForProtectedKeyFile(),
                    'key'       => null,
                    'keyfile'   => $this->getProtectedPrivateKeyFile(),
                ];
        }

        return [
            'autologin' => $autologin,
            'password'  => $this->getPasswordForProtectedKeyFile(),
            'key'       => $this->getProtectedPrivateKeyContents(),
            'keyfile'   => $this->getProtectedPrivateKeyFile(),
        ];
    }
}

<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\Unit;

use Neskodi\SSHCommander\Tests\Mocks\MockSSHConfigMissingDefaultFile;
use Neskodi\SSHCommander\Exceptions\ConfigFileMissingException;
use Neskodi\SSHCommander\Exceptions\ConfigValidationException;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Tests\TestCase;
use Neskodi\SSHCommander\SSHCommander;
use Neskodi\SSHCommander\SSHConfig;
use BadMethodCallException;

class SSHConfigTest extends TestCase
{
    /**
     * @return array
     */
    protected function getBaseConfigWithMissingCredentials(): array
    {
        $base = $this->getTestConfigAsArray(self::CONFIG_SECONDARY_ONLY);
        $base = array_merge($base, [
            'host' => 'example.com',
            'user' => 'foo',
        ]);

        return $base;
    }

    public function testConstructorWithEmptyHost(): void
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = '';
        $arrConfig['user']     = 'valid';
        $arrConfig['password'] = 'valid';

        $config = new SSHConfig($arrConfig);
        $config->set('autologin', true);
        $this->assertInstanceOf(SSHConfigInterface::class, $config);
        $this->expectException(ConfigValidationException::class);

        new SSHCommander($config);
    }

    public function testConstructorWithBlankHost(): void
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = '     ';
        $arrConfig['user']     = 'valid';
        $arrConfig['password'] = 'valid';

        $config = new SSHConfig($arrConfig);
        $config->set('autologin', true);
        $this->assertInstanceOf(SSHConfigInterface::class, $config);
        $this->expectException(ConfigValidationException::class);

        new SSHCommander($config);
    }

    public function testConstructorWithWrongHostVartype(): void
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = ['invalid'];
        $arrConfig['user']     = 'valid';
        $arrConfig['password'] = 'valid';

        $config = new SSHConfig($arrConfig);
        $config->set('autologin', true);
        $this->assertInstanceOf(SSHConfigInterface::class, $config);
        $this->expectException(ConfigValidationException::class);

        new SSHCommander($config);
    }

    public function testConstructorWithEmptyUser(): void
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = 'valid';
        $arrConfig['user']     = '';
        $arrConfig['password'] = 'valid';

        $config = new SSHConfig($arrConfig);
        $config->set('autologin', true);
        $this->assertInstanceOf(SSHConfigInterface::class, $config);
        $this->expectException(ConfigValidationException::class);

        new SSHCommander($config);
    }

    public function testConstructorWithBlankUser(): void
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = 'valid';
        $arrConfig['user']     = '     ';
        $arrConfig['password'] = 'valid';

        $config = new SSHConfig($arrConfig);
        $config->set('autologin', true);
        $this->assertInstanceOf(SSHConfigInterface::class, $config);
        $this->expectException(ConfigValidationException::class);

        new SSHCommander($config);
    }

    public function testConstructorWithWrongUserVartype(): void
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = 'valid';
        $arrConfig['user']     = ['invalid'];
        $arrConfig['password'] = 'valid';

        $config = new SSHConfig($arrConfig);
        $config->set('autologin', true);
        $this->assertInstanceOf(SSHConfigInterface::class, $config);
        $this->expectException(ConfigValidationException::class);

        new SSHCommander($config);
    }

    public function testConstructorAcceptsEmptyPassword(): void
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = 'valid';
        $arrConfig['user']     = 'valid';
        $arrConfig['password'] = '';

        $config = new SSHConfig($arrConfig);

        $this->assertSame('', $config->getPassword());
    }

    public function testConstructorWithWrongPasswordVartype(): void
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = 'valid';
        $arrConfig['user']     = 'valid';
        $arrConfig['password'] = ['invalid'];

        $config = new SSHConfig($arrConfig);
        $config->set('autologin', true);
        $this->assertInstanceOf(SSHConfigInterface::class, $config);
        $this->expectException(ConfigValidationException::class);

        new SSHCommander($config);
    }

    public function testConstructorWithEmptyKey(): void
    {
        $arrConfig         = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host'] = 'valid';
        $arrConfig['user'] = 'valid';
        $arrConfig['key']  = '';

        $config = new SSHConfig($arrConfig);
        $config->set('autologin', true);
        $this->assertInstanceOf(SSHConfigInterface::class, $config);
        $this->expectException(ConfigValidationException::class);

        new SSHCommander($config);
    }

    public function testConstructorWithBlankKey(): void
    {
        $arrConfig         = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host'] = 'valid';
        $arrConfig['user'] = 'valid';
        $arrConfig['key']  = '     ';

        $config = new SSHConfig($arrConfig);
        $config->set('autologin', true);
        $this->assertInstanceOf(SSHConfigInterface::class, $config);
        $this->expectException(ConfigValidationException::class);

        new SSHCommander($config);
    }

    public function testConstructorWithWrongKeyVartype(): void
    {
        $arrConfig         = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host'] = 'valid';
        $arrConfig['user'] = 'valid';
        $arrConfig['key']  = ['invalid'];

        $config = new SSHConfig($arrConfig);
        $config->set('autologin', true);
        $this->assertInstanceOf(SSHConfigInterface::class, $config);
        $this->expectException(ConfigValidationException::class);

        new SSHCommander($config);
    }

    public function testConstructorWithEmptyKeyfile(): void
    {
        $arrConfig            = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']    = 'valid';
        $arrConfig['user']    = 'valid';
        $arrConfig['keyfile'] = '';

        $config = new SSHConfig($arrConfig);
        $config->set('autologin', true);
        $this->assertInstanceOf(SSHConfigInterface::class, $config);
        $this->expectException(ConfigValidationException::class);

        new SSHCommander($config);
    }

    public function testConstructorWithInaccessibleKeyfile(): void
    {
        $arrConfig            = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']    = 'valid';
        $arrConfig['user']    = 'valid';
        $arrConfig['keyfile'] = '/no/such/file';

        $config = new SSHConfig($arrConfig);
        $config->set('autologin', true);
        $this->assertInstanceOf(SSHConfigInterface::class, $config);
        $this->expectException(ConfigValidationException::class);

        new SSHCommander($config);
    }

    public function testConstructorWithoutCredential(): void
    {
        $arrConfig         = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host'] = 'valid';
        $arrConfig['user'] = 'valid';

        $config = new SSHConfig($arrConfig);
        $config->set('autologin', true);
        $this->assertInstanceOf(SSHConfigInterface::class, $config);
        $this->expectException(ConfigValidationException::class);

        new SSHCommander($config);
    }

    public function testConstructorWithValidPortAsString(): void
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = 'valid';
        $arrConfig['port']     = '1234';
        $arrConfig['user']     = 'valid';
        $arrConfig['password'] = 'valid';

        $config = new SSHConfig($arrConfig);

        $this->assertSame(1234, $config->getPort());
    }

    public function testConstructorWithValidPortAsInteger(): void
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = 'valid';
        $arrConfig['port']     = 1234;
        $arrConfig['user']     = 'valid';
        $arrConfig['password'] = 'valid';

        $config = new SSHConfig($arrConfig);

        $this->assertSame(1234, $config->getPort());
    }

    public function testConstructorWithEmptyPort(): void
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = 'valid';
        $arrConfig['port']     = '';
        $arrConfig['user']     = 'valid';
        $arrConfig['password'] = 'valid';

        $config = new SSHConfig($arrConfig);
        $config->set('autologin', true);
        $this->assertInstanceOf(SSHConfigInterface::class, $config);
        $this->expectException(ConfigValidationException::class);

        new SSHCommander($config);
    }

    public function testConstructorWithBlankPort(): void
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = 'valid';
        $arrConfig['port']     = '     ';
        $arrConfig['user']     = 'valid';
        $arrConfig['password'] = 'valid';

        $config = new SSHConfig($arrConfig);
        $config->set('autologin', true);
        $this->assertInstanceOf(SSHConfigInterface::class, $config);
        $this->expectException(ConfigValidationException::class);

        new SSHCommander($config);
    }

    public function testConstructorWithWrongPortVartype(): void
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = 'valid';
        $arrConfig['port']     = ['1234'];
        $arrConfig['user']     = 'valid';
        $arrConfig['password'] = 'valid';

        $config = new SSHConfig($arrConfig);
        $config->set('autologin', true);
        $this->assertInstanceOf(SSHConfigInterface::class, $config);
        $this->expectException(ConfigValidationException::class);

        new SSHCommander($config);
    }

    public function testSet(): void
    {
        $arrConfig = $this->getTestConfigAsArray();

        $config = new SSHConfig($arrConfig);

        $config->set('testkey', 'testvalue');

        $this->assertEquals('testvalue', $config->get('testkey'));
    }

    public function testSetFromArray(): void
    {
        $arrConfig = $this->getTestConfigAsArray();

        $arrNewValues = [
            'host'     => '_host',
            'port'     => 1234,
            'user'     => '_user',
            'password' => '_pass',
            'key'      => '_key',
            'keyfile'  => '_keyfile',
        ];

        $config = new SSHConfig($arrConfig);

        $config->set($arrNewValues);

        $this->assertEquals($arrNewValues['host'], $config->getHost());
        $this->assertEquals($arrNewValues['port'], $config->getPort());
        $this->assertEquals($arrNewValues['user'], $config->getUser());
        $this->assertEquals($arrNewValues['password'], $config->getPassword());
        $this->assertEquals($arrNewValues['key'], $config->getKey());
        $this->assertEquals($arrNewValues['keyfile'], $config->getKeyfile());
    }

    public function testSetConfigFileLocation(): void
    {
        $location = '/x/files';

        SSHConfig::setUserConfigFileLocation($location);

        $this->assertEquals($location, SSHConfig::getConfigFileLocation());

        SSHConfig::resetConfigFileLocation();
    }

    public function testResetConfigFileLocation(): void
    {
        $location = '/x/files';
        $default  = SSHConfig::getDefaultConfigFileLocation();

        SSHConfig::setUserConfigFileLocation($location);

        $this->assertEquals($location, SSHConfig::getConfigFileLocation());

        SSHConfig::resetConfigFileLocation();

        $this->assertEquals($default, SSHConfig::getConfigFileLocation());
    }

    public function testGetDefaultConfigFileLocation(): void
    {
        $default = SSHConfig::getDefaultConfigFileLocation();

        $this->assertStringEndsWith('config.php', $default);
    }

    public function testValidMagicGetters(): void
    {
        $arrConfig = $this->getTestConfigAsArray();

        $config = new SSHConfig($arrConfig);

        $config->set('test_key', 'testvalue');

        $this->assertEquals('testvalue', $config->getTestKey());
    }

    public function testInvalidMagicGetters(): void
    {
        $arrConfig = $this->getTestConfigAsArray();

        $config = new SSHConfig($arrConfig);

        $this->expectException(BadMethodCallException::class);

        $config->noSuchGetter();
    }

    public function testSelectCredentialSelectsKey(): void
    {
        $base = $this->getBaseConfigWithMissingCredentials();

        $credentials = [
            'key'      => 'valid',
            'keyfile'  => $this->getUnprotectedPrivateKeyFile(),
            'password' => 'valid',
        ];

        $config = new SSHConfig(array_merge($base, $credentials));

        $this->assertEquals(
            SSHConfig::CREDENTIAL_KEY,
            $config->selectCredential()
        );
    }

    public function testSelectCredentialSelectsKeyfileIfKeyIsMissing(): void
    {
        $base = $this->getBaseConfigWithMissingCredentials();

        $credentials = [
            'key'      => null,
            'keyfile'  => $this->getUnprotectedPrivateKeyFile(),
            'password' => 'valid',
        ];

        $config = new SSHConfig(array_merge($base, $credentials));

        $this->assertEquals(
            SSHConfig::CREDENTIAL_KEYFILE,
            $config->selectCredential()
        );
    }

    public function testSelectCredentialSelectsPassword(): void
    {
        $base = $this->getBaseConfigWithMissingCredentials();

        $credentials = [
            'key'      => null,
            'keyfile'  => null,
            'password' => 'valid',
        ];

        $config = new SSHConfig(array_merge($base, $credentials));

        $this->assertEquals(
            SSHConfig::CREDENTIAL_PASSWORD,
            $config->selectCredential()
        );
    }

    public function testGettingAllValues(): void
    {
        $arrConfig = $this->getTestConfigAsArray();

        $config = new SSHConfig($arrConfig);

        $this->assertEquals($arrConfig, $config->all());
    }

    public function testValidateInvalidConfig(): void
    {
        $arrConfig = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );

        $config = new SSHConfig($arrConfig);

        $this->expectException(ConfigValidationException::class);

        $config->validate();
    }

    public function testInaccessibleDefaultConfigFile(): void
    {
        $arrConfig = $this->getTestConfigAsArray(
            self::CONFIG_CONNECTION_ONLY
        );

        $this->expectException(ConfigFileMissingException::class);

        new MockSSHConfigMissingDefaultFile($arrConfig);
    }

    public function testValidateValidConfig(): void
    {
        $arrConfig = $this->getTestConfigAsArray(
            self::CONFIG_CONNECTION_ONLY
        );

        $config = new SSHConfig($arrConfig);
        $config->validate();

        $this->assertInstanceOf(SSHConfigInterface::class, $config);
    }

    public function testGetPortReturnsNullIfPortIsNotSet(): void
    {
        $config = new SSHConfig(['port' => null]);

        $this->assertNull($config->getPort());
    }
}

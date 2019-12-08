<?php

namespace Neskodi\SSHCommander\Tests\Unit;

use Neskodi\SSHCommander\Exceptions\ConfigFileMissingException;
use Neskodi\SSHCommander\Exceptions\ConfigValidationException;
use Neskodi\SSHCommander\Interfaces\SSHConfigInterface;
use Neskodi\SSHCommander\Tests\Mocks\MockSSHConfig;
use Neskodi\SSHCommander\Tests\TestCase;
use Neskodi\SSHCommander\SSHConfig;
use BadMethodCallException;
use Exception;

class SSHConfigTest extends TestCase
{
    public function testConstructorWithEmptyConnectionInfo()
    {
        $arrConfig = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );

        $this->expectException(ConfigValidationException::class);

        new SSHConfig($arrConfig);
    }

    public function testConstructorWithDisabledValidation()
    {
        $arrConfig         = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['port'] = 22;

        $config = new SSHConfig($arrConfig, false);

        $this->assertEquals($arrConfig, $config->all());
    }

    public function testConstructorWithEmptyHost()
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = '';
        $arrConfig['user']     = 'valid';
        $arrConfig['password'] = 'valid';

        $this->expectException(ConfigValidationException::class);

        new SSHConfig($arrConfig);
    }

    public function testConstructorWithBlankHost()
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = '     ';
        $arrConfig['user']     = 'valid';
        $arrConfig['password'] = 'valid';

        $this->expectException(ConfigValidationException::class);

        new SSHConfig($arrConfig);
    }

    public function testConstructorWithWrongHostVartype()
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = ['invalid'];
        $arrConfig['user']     = 'valid';
        $arrConfig['password'] = 'valid';

        $this->expectException(ConfigValidationException::class);

        new SSHConfig($arrConfig);
    }

    public function testConstructorWithEmptyUser()
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = 'valid';
        $arrConfig['user']     = '';
        $arrConfig['password'] = 'valid';

        $this->expectException(ConfigValidationException::class);

        new SSHConfig($arrConfig);
    }

    public function testConstructorWithBlankUser()
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = 'valid';
        $arrConfig['user']     = '     ';
        $arrConfig['password'] = 'valid';

        $this->expectException(ConfigValidationException::class);

        new SSHConfig($arrConfig);
    }

    public function testConstructorWithWrongUserVartype()
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = 'valid';
        $arrConfig['user']     = ['invalid'];
        $arrConfig['password'] = 'valid';

        $this->expectException(ConfigValidationException::class);

        new SSHConfig($arrConfig);
    }

    public function testConstructorAcceptsEmptyPassword()
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

    public function testConstructorWithWrongPasswordVartype()
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = 'valid';
        $arrConfig['user']     = 'valid';
        $arrConfig['password'] = ['invalid'];

        $this->expectException(ConfigValidationException::class);

        new SSHConfig($arrConfig);
    }

    public function testConstructorWithEmptyKey()
    {
        $arrConfig         = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host'] = 'valid';
        $arrConfig['user'] = 'valid';
        $arrConfig['key']  = '';

        $this->expectException(ConfigValidationException::class);

        new SSHConfig($arrConfig);
    }

    public function testConstructorWithBlankKey()
    {
        $arrConfig         = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host'] = 'valid';
        $arrConfig['user'] = 'valid';
        $arrConfig['key']  = '     ';

        $this->expectException(ConfigValidationException::class);

        new SSHConfig($arrConfig);
    }

    public function testConstructorWithWrongKeyVartype()
    {
        $arrConfig         = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host'] = 'valid';
        $arrConfig['user'] = 'valid';
        $arrConfig['key']  = ['invalid'];

        $this->expectException(ConfigValidationException::class);

        new SSHConfig($arrConfig);
    }

    public function testConstructorWithEmptyKeyfile()
    {
        $arrConfig            = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']    = 'valid';
        $arrConfig['user']    = 'valid';
        $arrConfig['keyfile'] = '';

        $this->expectException(ConfigValidationException::class);

        new SSHConfig($arrConfig);
    }

    public function testConstructorWithInaccessibleKeyfile()
    {
        $arrConfig            = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']    = 'valid';
        $arrConfig['user']    = 'valid';
        $arrConfig['keyfile'] = '/no/such/file';

        $this->expectException(ConfigValidationException::class);

        new SSHConfig($arrConfig);
    }

    public function testConstructorWithoutCredential()
    {
        $arrConfig         = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host'] = 'valid';
        $arrConfig['user'] = 'valid';

        $this->expectException(ConfigValidationException::class);

        new SSHConfig($arrConfig);
    }

    public function testConstructorWithValidPortAsString()
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

    public function testConstructorWithValidPortAsInteger()
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

    public function testConstructorWithEmptyPort()
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = 'valid';
        $arrConfig['port']     = '';
        $arrConfig['user']     = 'valid';
        $arrConfig['password'] = 'valid';

        $this->expectException(ConfigValidationException::class);

        new SSHConfig($arrConfig);
    }

    public function testConstructorWithBlankPort()
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = 'valid';
        $arrConfig['port']     = '     ';
        $arrConfig['user']     = 'valid';
        $arrConfig['password'] = 'valid';

        $this->expectException(ConfigValidationException::class);

        new SSHConfig($arrConfig);
    }

    public function testConstructorWithWrongPortVartype()
    {
        $arrConfig             = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );
        $arrConfig['host']     = 'valid';
        $arrConfig['port']     = ['1234'];
        $arrConfig['user']     = 'valid';
        $arrConfig['password'] = 'valid';

        $this->expectException(ConfigValidationException::class);

        new SSHConfig($arrConfig);
    }

    public function testSet()
    {
        $arrConfig = $this->getTestConfigAsArray();

        $config = new SSHConfig($arrConfig);

        $config->set('testkey', 'testvalue');

        $this->assertEquals('testvalue', $config->get('testkey'));
    }

    public function testSetInvalidValue()
    {
        $arrConfig = $this->getTestConfigAsArray();

        $config = new SSHConfig($arrConfig);

        $this->expectException(ConfigValidationException::class);

        $config->set('port', 'San Marino');
    }

    public function testSetFromArray()
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

        $config->setFromArray($arrNewValues);

        $this->assertEquals($arrNewValues['host'], $config->getHost());
        $this->assertEquals($arrNewValues['port'], $config->getPort());
        $this->assertEquals($arrNewValues['user'], $config->getUser());
        $this->assertEquals($arrNewValues['password'], $config->getPassword());
        $this->assertEquals($arrNewValues['key'], $config->getKey());
        $this->assertEquals($arrNewValues['keyfile'], $config->getKeyfile());
    }

    public function testSetConfigFileLocation()
    {
        $location = '/x/files';

        SSHConfig::setConfigFileLocation($location);

        $this->assertEquals($location, SSHConfig::getConfigFileLocation());

        SSHConfig::resetConfigFileLocation();
    }

    public function testResetConfigFileLocation()
    {
        $location = '/x/files';
        $default  = SSHConfig::getDefaultConfigFileLocation();

        SSHConfig::setConfigFileLocation($location);

        $this->assertEquals($location, SSHConfig::getConfigFileLocation());

        SSHConfig::resetConfigFileLocation();

        $this->assertEquals($default, SSHConfig::getConfigFileLocation());
    }

    public function testGetDefaultConfigFileLocation()
    {
        $default = SSHConfig::getDefaultConfigFileLocation();

        $this->assertStringEndsWith('config.php', $default);
    }

    public function testValidMagicGetters()
    {
        $arrConfig = $this->getTestConfigAsArray();

        $config = new SSHConfig($arrConfig);

        $config->set('test_key', 'testvalue');

        $this->assertEquals('testvalue', $config->getTestKey());
    }

    public function testInvalidMagicGetters()
    {
        $arrConfig = $this->getTestConfigAsArray();

        $config = new SSHConfig($arrConfig);

        $this->expectException(BadMethodCallException::class);

        $config->noSuchGetter();
    }

    public function testSelectCredentialSelectsKey()
    {
        $base = $this->getBaseConfigWithoutCredentials();

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

    public function testSelectCredentialSelectsKeyfileIfKeyIsMissing()
    {
        $base = $this->getBaseConfigWithoutCredentials();

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

    public function testSelectCredentialSelectsPassword()
    {
        $base = $this->getBaseConfigWithoutCredentials();

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

    public function testAll()
    {
        $arrConfig = $this->getTestConfigAsArray();

        $config = new SSHConfig($arrConfig);

        $this->assertEquals($arrConfig, $config->all());
    }

    public function testValidateInvalidConfig()
    {
        $arrConfig = $this->getTestConfigAsArray(
            self::CONFIG_SECONDARY_ONLY
        );

        $config = new SSHConfig([], false);

        $this->expectException(ConfigValidationException::class);

        $config->validate($arrConfig);
    }

    public function testInaccessibleDefaultConfigFile()
    {
        $arrConfig = $this->getTestConfigAsArray(
            self::CONFIG_CONNECTION_ONLY
        );

        $this->expectException(ConfigFileMissingException::class);

        new MockSSHConfig($arrConfig);
    }

    /** @noinspection PhpUndefinedVariableInspection */
    public function testValidateValidConfig()
    {
        $arrConfig = $this->getTestConfigAsArray(
            self::CONFIG_CONNECTION_ONLY
        );

        $config = new SSHConfig([], false);

        try {
            $result = $config->validate($arrConfig);
        } catch (Exception $e) {
            $this->fail(
                'Validation of valid config array still throws an Exception'
            );
        }

        $this->assertInstanceOf(SSHConfigInterface::class, $result);
    }

    public function testGetPortReturnsNullIfPortIsNotSet()
    {
        $config = new SSHConfig(['port' => null], false);

        $this->assertNull($config->getPort());
    }


    /**
     * @return array
     */
    protected function getBaseConfigWithoutCredentials(): array
    {
        $base = $this->getTestConfigAsArray(self::CONFIG_SECONDARY_ONLY);
        $base = array_merge($base, [
            'host' => 'example.com',
            'user' => 'foo',
        ]);

        return $base;
    }
}

<?php /** @noinspection PhpIncludeInspection */

namespace Neskodi\SSHCommander\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Neskodi\SSHCommander\SSHConfig;

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
}

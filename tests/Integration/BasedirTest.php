<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Tests\IntegrationTestCase;

class BasedirTest extends IntegrationTestCase
{
    public function testIsolatedSetBasedirFromGlobalConfig(): void
    {
        $basedir = '/usr';
        $tested  = ['basedir' => $basedir];
        $config  = array_merge($this->sshOptions, $tested);

        // check that basedir picked for this test differs from default
        $defaultConfig = $this->getTestConfigAsArray();
        $this->assertNotEquals($defaultConfig['basedir'], $basedir);

        $commander = $this->getSSHCommander($config);

        $outputLines = $commander->runIsolated('pwd')->getOutput();

        $this->assertContains($basedir, $outputLines);
    }

    public function testIsolatedSetBasedirInCommandConfig(): void
    {
        $basedir = '/usr';
        $tested  = ['basedir' => $basedir];

        // check that basedir picked for this test differs from default
        $defaultConfig = $this->getTestConfigAsArray();
        $this->assertNotEquals($defaultConfig['basedir'], $basedir);

        $commander = $this->getSSHCommander($this->sshOptions);

        $outputLines = $commander->runIsolated('pwd', $tested)->getOutput();

        $this->assertContains($basedir, $outputLines);
    }

    public function testIsolatedSetBasedirOnTheFly(): void
    {
        $basedir = '/usr';

        // check that basedir picked for this test differs from default
        $defaultConfig = $this->getTestConfigAsArray();
        $this->assertNotEquals($defaultConfig['basedir'], $basedir);

        $commander = $this->getSSHCommander($this->sshOptions);
        $commander->basedir($basedir);

        $outputLines = $commander->runIsolated('pwd')->getOutput();

        $this->assertContains($basedir, $outputLines);
    }

    public function testInteractiveSetBasedirFromGlobalConfig(): void
    {
        $basedir = '/usr';
        $tested  = ['basedir' => $basedir];
        $config  = array_merge($this->sshOptions, $tested);

        // check that basedir picked for this test differs from default
        $defaultConfig = $this->getTestConfigAsArray();
        $this->assertNotEquals($defaultConfig['basedir'], $basedir);

        $commander = $this->getSSHCommander($config);

        $outputLines = $commander->run('pwd')->getOutput();

        $this->assertContains($basedir, $outputLines);
    }

    public function testInteractiveSetBasedirInCommandConfig(): void
    {
        $basedir = '/usr';
        $tested  = ['basedir' => $basedir];

        // check that basedir picked for this test differs from default
        $defaultConfig = $this->getTestConfigAsArray();
        $this->assertNotEquals($defaultConfig['basedir'], $basedir);

        $commander = $this->getSSHCommander($this->sshOptions);

        $outputLines = $commander->run('pwd', $tested)->getOutput();

        $this->assertContains($basedir, $outputLines);
    }

    public function testInteractiveSetBasedirOnTheFly(): void
    {
        $basedir = '/usr';

        // check that basedir picked for this test differs from default
        $defaultConfig = $this->getTestConfigAsArray();
        $this->assertNotEquals($defaultConfig['basedir'], $basedir);

        $commander = $this->getSSHCommander($this->sshOptions);
        $commander->basedir($basedir);

        $outputLines = $commander->run('pwd')->getOutput();

        $this->assertContains($basedir, $outputLines);
    }
}

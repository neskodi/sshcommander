<?php /** @noinspection PhpUnhandledExceptionInspection */

/** @noinspection DuplicatedCode */

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Exceptions\CommandRunException;
use Neskodi\SSHCommander\Tests\IntegrationTestCase;
use Neskodi\SSHCommander\SSHCommander;
use Neskodi\SSHCommander\SSHConfig;
use RuntimeException;

class ErrorHandlingTest extends IntegrationTestCase
{
    public function testIsolatedSimple()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray([
            'break_on_error' => SSHConfig::BREAK_ON_ERROR_NEVER
        ]);

        $commander = new SSHCommander($config);

        $result = $commander->runIsolated('cd /no/such/dir');

        $this->assertStringContainsStringIgnoringCase('no such', (string)$result);
        $this->assertTrue($result->isError());
    }

    public function testIsolatedSimpleBOE()
    {
        $this->expectException(CommandRunException::class);

        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray([
            'break_on_error' => SSHConfig::BREAK_ON_ERROR_ALWAYS
        ]);

        $commander = new SSHCommander($config);

        $commander->runIsolated('cd /no/such/dir');
    }

    public function testIsolatedSimpleBOESoftfail()
    {
        $this->expectException(CommandRunException::class);

        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray([
            'break_on_error' => SSHConfig::BREAK_ON_ERROR_LAST_SUBCOMMAND
        ]);

        $commander = new SSHCommander($config);

        $commander->runIsolated('cd /no/such/dir');
    }

    public function testIsolatedCompound()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray([
            'break_on_error' => SSHConfig::BREAK_ON_ERROR_NEVER
        ]);

        $commander = new SSHCommander($config);

        $result = $commander->runIsolated('cd /no/such/dir;echo AAA');

        $lines = $result->getOutput();

        $this->assertStringContainsStringIgnoringCase('no such', $lines[0]);
        $this->assertEquals('AAA', end($lines));
        $this->assertTrue($result->isOk());
    }

    public function testIsolatedCompoundBOE()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray([
            'break_on_error' => SSHConfig::BREAK_ON_ERROR_ALWAYS
        ]);

        $commander = new SSHCommander($config);

        $exceptionWasThrown = false;

        try {
            $commander->runIsolated('cd /no/such/dir;echo AAA');
        } catch (CommandRunException $e) {
            $exceptionWasThrown = true;
            $result = $commander->getIsolatedCommandRunner()->getResult();
            $lines  = $result->getOutput();

            $this->assertNotContains('AAA', $lines);
        } finally {
            $this->assertTrue($exceptionWasThrown);
        }

    }

    public function testIsolatedCompoundBOESoftfail()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray([
            'break_on_error' => SSHConfig::BREAK_ON_ERROR_LAST_SUBCOMMAND
        ]);

        $commander = new SSHCommander($config);

        $result = $commander->runIsolated('cd /no/such/dir;echo AAA');
        $lines  = $result->getOutput();

        $this->assertStringContainsStringIgnoringCase('no such', (string)$result);
        $this->assertContains('AAA', $lines);
        $this->assertTrue($result->isOk());
    }



    public function testInteractiveSimple()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray([
            'break_on_error' => SSHConfig::BREAK_ON_ERROR_NEVER
        ]);

        $commander = new SSHCommander($config);
        $results   = [];

        $results[] = $commander->run('cd /tmp');
        $results[] = $commander->run('cd /no/such/dir');
        $results[] = $commander->run('pwd');

        $this->assertTrue($results[0]->isOk());

        $this->assertTrue($results[1]->isError());
        $this->assertStringContainsStringIgnoringCase('no such', (string)$results[1]);

        $this->assertTrue($results[2]->isOk());
        $this->assertEquals('/tmp', (string)$results[2]);
    }

    public function testInteractiveSimpleBOE()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray([
            'break_on_error' => SSHConfig::BREAK_ON_ERROR_ALWAYS
        ]);

        $commander = new SSHCommander($config);

        $results            = [];
        $exceptionWasThrown = false;

        try {
            $results['AAA']   = $commander->run('echo AAA');
            $results['error'] = $commander->run('cd /no/such/dir');
            $results['BBB']   = $commander->run('echo BBB');
        } catch (CommandRunException $exception) {
            $exceptionWasThrown = true;

            $this->assertTrue($results['AAA']->isOk());
            $this->assertEquals('AAA', (string)$results['AAA']);

            $this->assertCount(1, $results);
        } finally {
            $this->assertTrue($exceptionWasThrown);
        }
    }

    public function testInteractiveSimpleBOESoftfail()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray([
            'break_on_error' => SSHConfig::BREAK_ON_ERROR_LAST_SUBCOMMAND
        ]);

        $commander = new SSHCommander($config);

        $results            = [];
        $exceptionWasThrown = false;

        try {
            $results[] = $commander->run('cd /tmp');
            $results[] = $commander->run('cd /no/such/dir;');
        } catch (CommandRunException $exception) {
            $exceptionWasThrown = true;

            $this->assertTrue($results[0]->isOk());

            $this->assertCount(1, $results);

            // test that the environment was preserved
            $further = $commander->run('pwd');
            $this->assertEquals('/tmp', (string)$further);
        } finally {
            $this->assertTrue($exceptionWasThrown);
        }
    }

    public function testInteractiveCompound()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray([
            'break_on_error' => SSHConfig::BREAK_ON_ERROR_NEVER
        ]);

        $commander = new SSHCommander($config);

        $results = [];

        $results[] = $commander->run('echo AAA');
        $results[] = $commander->run('cd /no/such/dir;echo BBB');
        $results[] = $commander->run('echo CCC');

        $this->assertTrue($results[0]->isOk());
        $this->assertEquals('AAA', (string)$results[0]);

        $this->assertTrue($results[1]->isOk());
        $this->assertStringContainsString('BBB', (string)$results[1]);
        $this->assertStringContainsStringIgnoringCase('no such', (string)$results[1]);

        $this->assertTrue($results[2]->isOk());
        $this->assertEquals('CCC', (string)$results[2]);
    }

    public function testInteractiveCompoundBOE()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray([
            'break_on_error' => SSHConfig::BREAK_ON_ERROR_ALWAYS
        ]);

        $commander = new SSHCommander($config);

        $results = [];
        $exceptionWasThrown = false;

        try {
            $results[] = $commander->run('cd /tmp');
            $results[] = $commander->run('cd /no/such/dir;echo BBB');
            $results[] = $commander->run('echo CCC');
        } catch (CommandRunException $exception) {
            $exceptionWasThrown = true;

            $this->assertTrue($results[0]->isOk());
            $this->assertCount(1, $results);
            $this->assertStringNotContainsString('BBB', (string)$results[0]);
            $this->assertStringNotContainsString('CCC', (string)$results[0]);
        } finally {
            $this->assertTrue($exceptionWasThrown);
        }
    }

    public function testInteractiveCompoundBOESoftfail()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray([
            'break_on_error' => SSHConfig::BREAK_ON_ERROR_LAST_SUBCOMMAND
        ]);

        $commander = new SSHCommander($config);

        $results = [];
        $exceptionWasThrown = false;

        try {
            $results[] = $commander->run('cd /tmp');
            $results[] = $commander->run('cd /no/such/dir');
            $results[] = $commander->run('echo BBB');
        } catch (CommandRunException $exception) {
            $exceptionWasThrown = true;

            $this->assertCount(1, $results);
            $this->assertTrue($results[0]->isOk());
            $this->assertStringNotContainsString('BBB', (string)$results[0]);

            // check that the environment was preserved
            $further = $commander->run('pwd');
            $this->assertEquals('/tmp', (string)$further);
        } finally {
            $this->assertTrue($exceptionWasThrown);
        }
    }

    public function testInteractiveCompoundBOESoftfailNotLast()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray([
            'break_on_error' => SSHConfig::BREAK_ON_ERROR_LAST_SUBCOMMAND
        ]);

        $commander = new SSHCommander($config);

        $results = [];

        $results[] = $commander->run('echo AAA');
        $results[] = $commander->run('cd /no/such/dir;echo BBB');
        $results[] = $commander->run('echo CCC');

        $this->assertEquals('AAA', (string)$results[0]);
        $this->assertTrue($results[0]->isOk());

        $lines = $results[1]->getOutput();
        $this->assertEquals('BBB', end($lines));
        $this->assertStringContainsStringIgnoringCase('no such', reset($lines));

        $this->assertEquals('CCC', (string)$results[2]);
        $this->assertTrue($results[2]->isOk());
    }
}

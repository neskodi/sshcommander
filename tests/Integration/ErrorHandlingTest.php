<?php /** @noinspection PhpUnhandledExceptionInspection */

/** @noinspection DuplicatedCode */

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Exceptions\AuthenticationException;
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
        $config->setFromArray(['break_on_error' => false]);

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
        $config->setFromArray(['break_on_error' => true]);

        $commander = new SSHCommander($config);

        $commander->runIsolated('cd /no/such/dir');
    }

    public function testIsolatedSimpleErrexit()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray(['break_on_error' => false]);

        $commander = new SSHCommander($config);

        $result = $commander->runIsolated('set -e;cd /no/such/dir');

        $this->assertStringContainsStringIgnoringCase('no such', (string)$result);
        $this->assertTrue($result->isError());
    }

    public function testIsolatedSimpleBOEErrexit()
    {
        $this->expectException(CommandRunException::class);

        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray(['break_on_error' => true]);

        $commander = new SSHCommander($config);

        $commander->runIsolated('set -e;cd /no/such/dir');
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
        $config->setFromArray(['break_on_error' => false]);

        $commander = new SSHCommander($config);

        $result = $commander->runIsolated('cd /no/such/dir;echo AAA');

        $lines = $result->getOutput();

        $this->assertStringContainsStringIgnoringCase('no such', $lines[0]);
        $this->assertEquals('AAA', end($lines));
        $this->assertTrue($result->isOk());
    }

    /**
     * We do not expect exception here despite BOE, because the last command
     * in the compound returns success.
     *
     * @throws AuthenticationException
     * @throws CommandRunException
     */
    public function testIsolatedCompoundBOE()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray(['break_on_error' => true]);

        $commander = new SSHCommander($config);

        $result = $commander->runIsolated('cd /no/such/dir;echo AAA');
        $lines  = $result->getOutput();

        $this->assertEquals('AAA', end($lines));
        $this->assertTrue($result->isOk());
    }

    public function testIsolatedCompoundErrexit()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray(['break_on_error' => false]);

        $commander = new SSHCommander($config);

        $result = $commander->runIsolated('set -e;cd /no/such/dir;echo AAA');
        $lines  = $result->getOutput();

        $this->assertStringContainsStringIgnoringCase('no such', (string)$result);
        $this->assertNotContains('AAA', $lines);
        $this->assertTrue($result->isError());
    }

    public function testIsolatedCompoundBOEErrexit()
    {
        $this->expectException(CommandRunException::class);

        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray(['break_on_error' => true]);

        $commander = new SSHCommander($config);

        $commander->runIsolated('set -e;cd /no/such/dir;echo AAA');
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
        $config->setFromArray(['break_on_error' => false]);

        $commander = new SSHCommander($config);
        $results   = [];

        $results[] = $commander->run('echo AAA');
        $results[] = $commander->run('cd /no/such/dir');
        $results[] = $commander->run('echo BBB');

        $this->assertTrue($results[0]->isOk());
        $this->assertEquals('AAA', (string)$results[0]);

        $this->assertTrue($results[1]->isError());
        $this->assertStringContainsStringIgnoringCase('no such', (string)$results[1]);

        $this->assertTrue($results[2]->isOk());
        $this->assertEquals('BBB', (string)$results[2]);
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
        $config->setFromArray(['break_on_error' => true]);

        $commander = new SSHCommander($config);

        $results            = [];
        $exceptionWasThrown = false;

        try {
            $results['AAA']   = $commander->run('echo AAA');
            $results['error'] = $commander->run('cd /no/such/dir');
            $results['BBB']   = $commander->run('echo BBB');
        } catch (CommandRunException $exceptionWasThrown) {
            $exceptionWasThrown = true;

            $this->assertTrue($results['AAA']->isOk());
            $this->assertEquals('AAA', (string)$results['AAA']);

            $this->assertCount(1, $results);
        } finally {
            $this->assertTrue($exceptionWasThrown);
        }
    }

    public function testInteractiveSimpleErrexit()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray(['break_on_error' => false]);

        $commander = new SSHCommander($config);

        $results = [];

        try {
            $results[] = $commander->run('set -e;');
            $results[] = $commander->run('echo AAA');
            $results[] = $commander->run('cd /no/such/dir'); // <-error
            $results[] = $commander->run('echo BBB');
        } catch (CommandRunException $exception) {
            $this->fail('CommandRunException was thrown improperly');
        }

        // set -e
        $this->assertTrue($results[0]->isOk());

        // echo AAA
        $this->assertEquals('AAA', (string)$results[1]);
        $this->assertTrue($results[1]->isOk());

        // cd /no/such/dir
        $this->assertTrue($results[2]->isError());

        // echo BBB
        $this->assertEquals('BBB', (string)$results[3]);
        $this->assertTrue($results[3]->isOk());
    }

    public function testInteractiveSimpleBOEErrexit()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray(['break_on_error' => true]);

        $commander = new SSHCommander($config);

        $results            = [];
        $exceptionWasThrown = false;

        try {
            $results[] = $commander->run('set -e;');
            $results[] = $commander->run('echo AAA');
            $results[] = $commander->run('cd /no/such/dir'); // <-error
            $results[] = $commander->run('echo BBB');
        } catch (CommandRunException $exception) {
            $exceptionWasThrown = true;

            // set -e
            $this->assertTrue($results[0]->isOk());

            // echo AAA
            $this->assertEquals('AAA', (string)$results[1]);
            $this->assertTrue($results[1]->isOk());

            $this->assertCount(2, $results);
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
        $config->setFromArray(['break_on_error' => false]);

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

    /**
     * We do not expect exception here despite BOE, because the last command
     * in the compound returns success.
     *
     * @throws AuthenticationException
     * @throws CommandRunException
     */
    public function testInteractiveCompoundBOE()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray(['break_on_error' => true]);

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

    public function testInteractiveCompoundErrexit()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray(['break_on_error' => false]);

        $commander = new SSHCommander($config);

        $results = [];

        try {
            $results[] = $commander->run('set -e;');
            $results[] = $commander->run('echo AAA');
            $results[] = $commander->run('cd /no/such/dir'); // <-error
            $results[] = $commander->run('echo BBB');
        } catch (CommandRunException $exception) {
            $this->fail('CommandRunException was thrown improperly');
        }

        // set -e
        $this->assertTrue($results[0]->isOk());

        // echo AAA
        $this->assertEquals('AAA', (string)$results[1]);
        $this->assertTrue($results[1]->isOk());

        // cd /no/such/dir
        $this->assertTrue($results[2]->isError());
        $this->assertStringContainsStringIgnoringCase('no such', (string)$results[2]);

        // echo BBB
        $this->assertEquals('BBB', (string)$results[3]);
        $this->assertTrue($results[3]->isOk());
    }

    public function testInteractiveCompoundBOEErrexit()
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $config = new SSHConfig($this->sshOptions);
        $config->setFromArray(['break_on_error' => true]);

        $commander = new SSHCommander($config);

        $results = [];
        $exceptionWasThrown = false;

        try {
            $results[] = $commander->run('set -e;');
            $results[] = $commander->run('echo AAA');
            $results[] = $commander->run('cd /no/such/dir'); // <-error
            $results[] = $commander->run('echo BBB');
        } catch (CommandRunException $exception) {
            $exceptionWasThrown = true;

            // set -e
            $this->assertTrue($results[0]->isOk());

            // echo AAA
            $this->assertEquals('AAA', (string)$results[1]);
            $this->assertTrue($results[1]->isOk());

            $this->assertCount(2, $results);
        } finally {
            $this->assertTrue($exceptionWasThrown);
        }
    }
}

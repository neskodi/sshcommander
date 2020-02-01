<?php /** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpRedundantCatchClauseInspection */
/** @noinspection DuplicatedCode */

namespace Neskodi\SSHCommander\Tests\Integration;

use Neskodi\SSHCommander\Interfaces\SSHCommanderInterface;
use Neskodi\SSHCommander\Exceptions\CommandRunException;
use Neskodi\SSHCommander\Tests\IntegrationTestCase;
use Neskodi\SSHCommander\SSHConfig;

class ErrorHandlingTest extends IntegrationTestCase
{
    public function testIsolatedSimple(): void
    {
        if (!$this->hasAuthCredentials()) {
            $this->markTestSkipped('Authentication credentials required to run this test');
        }

        $config = new SSHConfig($this->sshOptions);
        $config->set('break_on_error', SSHConfig::BREAK_ON_ERROR_NEVER);

        $commander = $this->getSSHCommander($config);

        $result = $commander->runIsolated('cd /no/such/dir');

        $this->assertStringContainsStringIgnoringCase('no such', (string)$result);
        $this->assertTrue($result->isError());
    }

    public function testIsolatedSimpleBOE(): void
    {
        if (!$this->hasAuthCredentials()) {
            $this->markTestSkipped('Authentication credentials required to run this test');
        }

        $this->expectException(CommandRunException::class);

        $config = new SSHConfig($this->sshOptions);
        $config->set('break_on_error', SSHConfig::BREAK_ON_ERROR_ALWAYS);

        $commander = $this->getSSHCommander($config);

        $commander->runIsolated('cd /no/such/dir');
    }

    public function testIsolatedSimpleBOESoftfail(): void
    {
        if (!$this->hasAuthCredentials()) {
            $this->markTestSkipped('Authentication credentials required to run this test');
        }

        $this->expectException(CommandRunException::class);

        $config = new SSHConfig($this->sshOptions);
        $config->set('break_on_error', SSHConfig::BREAK_ON_ERROR_LAST_SUBCOMMAND);

        $commander = $this->getSSHCommander($config);

        $commander->runIsolated('cd /no/such/dir');
    }

    public function testIsolatedCompound(): void
    {
        if (!$this->hasAuthCredentials()) {
            $this->markTestSkipped('Authentication credentials required to run this test');
        }

        $config = new SSHConfig($this->sshOptions);
        $config->set('break_on_error', SSHConfig::BREAK_ON_ERROR_NEVER);

        $commander = $this->getSSHCommander($config);

        $result = $commander->runIsolated('cd /no/such/dir;echo AAA');

        $lines = $result->getOutput();

        $this->assertStringContainsStringIgnoringCase('no such', $lines[0]);
        $this->assertEquals('AAA', end($lines));
        $this->assertTrue($result->isOk());
    }

    public function testIsolatedCompoundBOE(): void
    {
        if (!$this->hasAuthCredentials()) {
            $this->markTestSkipped('Authentication credentials required to run this test');
        }

        $config = new SSHConfig($this->sshOptions);
        $config->set('break_on_error', SSHConfig::BREAK_ON_ERROR_ALWAYS);

        $commander = $this->getSSHCommander($config);

        $exceptionWasThrown = false;

        try {
            $commander->runIsolated('cd /no/such/dir;echo AAA');
        } catch (CommandRunException $e) {
            $exceptionWasThrown = true;
            $result             = $commander->getIsolatedCommandRunner()->getResult();
            $lines              = $result->getOutput();

            $this->assertNotContains('AAA', $lines);
        } finally {
            $this->assertTrue($exceptionWasThrown);
        }

    }

    public function testIsolatedCompoundBOESoftfail(): void
    {
        if (!$this->hasAuthCredentials()) {
            $this->markTestSkipped('Authentication credentials required to run this test');
        }

        $config = new SSHConfig($this->sshOptions);
        $config->set('break_on_error', SSHConfig::BREAK_ON_ERROR_LAST_SUBCOMMAND);

        $commander = $this->getSSHCommander($config);

        $result = $commander->runIsolated('cd /no/such/dir;echo AAA');
        $lines  = $result->getOutput();

        $this->assertStringContainsStringIgnoringCase('no such', (string)$result);
        $this->assertContains('AAA', $lines);
        $this->assertTrue($result->isOk());
    }

    public function testInteractiveSimple(): void
    {
        if (!$this->hasAuthCredentials()) {
            $this->markTestSkipped('Authentication credentials required to run this test');
        }

        $config = new SSHConfig($this->sshOptions);
        $config->set('break_on_error', SSHConfig::BREAK_ON_ERROR_NEVER);

        $commander = $this->getSSHCommander($config);
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

    public function testInteractiveSimpleBOE(): void
    {
        if (!$this->hasAuthCredentials()) {
            $this->markTestSkipped('Authentication credentials required to run this test');
        }

        $config = new SSHConfig($this->sshOptions);
        $config->set('break_on_error', SSHConfig::BREAK_ON_ERROR_ALWAYS);

        $commander = $this->getSSHCommander($config);

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

    public function testInteractiveSimpleBOESoftfail(): void
    {
        if (!$this->hasAuthCredentials()) {
            $this->markTestSkipped('Authentication credentials required to run this test');
        }

        $config = new SSHConfig($this->sshOptions);
        $config->set('break_on_error', SSHConfig::BREAK_ON_ERROR_LAST_SUBCOMMAND);

        $commander = $this->getSSHCommander($config);

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

    public function testInteractiveCompound(): void
    {
        if (!$this->hasAuthCredentials()) {
            $this->markTestSkipped('Authentication credentials required to run this test');
        }

        $config = new SSHConfig($this->sshOptions);
        $config->set('break_on_error', SSHConfig::BREAK_ON_ERROR_NEVER);

        $commander = $this->getSSHCommander($config);

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

    /** @noinspection PhpRedundantCatchClauseInspection */
    public function testInteractiveCompoundBOE(): void
    {
        if (!$this->hasAuthCredentials()) {
            $this->markTestSkipped('Authentication credentials required to run this test');
        }

        $config = new SSHConfig($this->sshOptions);
        $config->set('break_on_error', SSHConfig::BREAK_ON_ERROR_ALWAYS);

        $commander = $this->getSSHCommander($config);

        $results            = [];
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

    /** @noinspection PhpRedundantCatchClauseInspection */
    public function testInteractiveCompoundBOESoftfail(): void
    {
        if (!$this->hasAuthCredentials()) {
            $this->markTestSkipped('Authentication credentials required to run this test');
        }

        $config = new SSHConfig($this->sshOptions);
        $config->set('break_on_error', SSHConfig::BREAK_ON_ERROR_LAST_SUBCOMMAND);

        $commander = $this->getSSHCommander($config);

        $results            = [];
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

    public function testInteractiveCompoundBOESoftfailNotLast(): void
    {
        if (!$this->hasAuthCredentials()) {
            $this->markTestSkipped('Authentication credentials required to run this test');
        }

        $config = new SSHConfig($this->sshOptions);
        $config->set('break_on_error', SSHConfig::BREAK_ON_ERROR_LAST_SUBCOMMAND);

        $commander = $this->getSSHCommander($config);

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

    public function testSetBOEOnTheFly(): void
    {
        if (!$this->hasAuthCredentials()) {
            $this->markTestSkipped('Authentication credentials required to run this test');
        }

        $config    = new SSHConfig($this->sshOptions);
        $commander = $this->getSSHCommander($config);


        // set to false and run a few commands
        $this->chainTestWithBOEFalse($commander);

        // set to true and run a few commands
        $this->chainTestWithBOETrue($commander);

        // set to softfail and run a few commands
        $this->chainTestWithBOESoftfail($commander);
    }

    /** @noinspection PhpRedundantCatchClauseInspection */
    public function testSetBOEPerCommand(): void
    {
        if (!$this->hasAuthCredentials()) {
            $this->markTestSkipped('Authentication credentials required to run this test');
        }

        $commander = $this->getSSHCommander($this->sshOptions);
        $commander->breakOnError(SSHConfig::BREAK_ON_ERROR_NEVER);

        $exceptionWasThrown = false;
        $results            = [];

        try {
            $results[] = $commander->run('cd /no/such/dir');
            $results[] = $commander->run(
                'cd /no/such/dir/1;echo BBB',
                ['break_on_error' => SSHConfig::BREAK_ON_ERROR_LAST_SUBCOMMAND]
            );
            $results[] = $commander->run(
                'cd /no/such/dir/2;echo CCC',
                ['break_on_error' => SSHConfig::BREAK_ON_ERROR_ALWAYS]
            );
        } catch (CommandRunException $exception) {
            $exceptionWasThrown = true;
            $strResults = implode(PHP_EOL, $results);

            $this->assertCount(2, $results);
            $this->assertStringContainsString('BBB', $strResults);
            $this->assertStringNotContainsString('CCC', $strResults);
        } finally {
            $this->assertTrue($exceptionWasThrown);
        }
    }

    protected function chainTestWithBOEFalse(SSHCommanderInterface $commander): void
    {
        $commander->breakOnError(SSHConfig::BREAK_ON_ERROR_NEVER);
        $this->assertEquals(
            SSHConfig::BREAK_ON_ERROR_NEVER,
            $commander->getConfig('break_on_error')
        );

        $results = [];

        $results[] = $commander->run('cd /no/such/dir/1');
        $results[] = $commander->run('cd /no/such/dir/2');
        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->isError());
        $this->assertTrue($results[1]->isError());
    }

    /** @noinspection PhpRedundantCatchClauseInspection */
    protected function chainTestWithBOETrue(SSHCommanderInterface $commander): void
    {
        $commander->breakOnError(SSHConfig::BREAK_ON_ERROR_ALWAYS);
        $this->assertEquals(
            SSHConfig::BREAK_ON_ERROR_ALWAYS,
            $commander->getConfig('break_on_error')
        );

        $exceptionWasThrown = false;
        $results            = [];

        try {
            $results[] = $commander->run('echo AAA');
            $results[] = $commander->run('cd /no/such/dir');
            $results[] = $commander->run('echo BBB');
        } catch (CommandRunException $exception) {
            $exceptionWasThrown = true;
            $this->assertCount(1, $results);
            $this->assertEquals('AAA', (string)$results[0]);
        } finally {
            $this->assertTrue($exceptionWasThrown);
        }
    }

    /** @noinspection PhpRedundantCatchClauseInspection */
    protected function chainTestWithBOESoftfail(SSHCommanderInterface $commander): void
    {
        $commander->breakOnError(SSHConfig::BREAK_ON_ERROR_LAST_SUBCOMMAND);
        $this->assertEquals(
            SSHConfig::BREAK_ON_ERROR_LAST_SUBCOMMAND,
            $commander->getConfig('break_on_error')
        );

        $exceptionWasThrown = false;
        $results            = [];

        try {
            $results[] = $commander->run('echo XXX');
            $results[] = $commander->run('cd /no/such/dir/1;echo YYY');
            $results[] = $commander->run('cd /no/such/dir/2;');
            $results[] = $commander->run('echo ZZZ');
        } catch (CommandRunException $exception) {
            $exceptionWasThrown = true;
            $this->assertCount(2, $results);
            $this->assertEquals('XXX', (string)$results[0]);
            $this->assertStringContainsString('YYY', (string)$results[1]);
        } finally {
            $this->assertTrue($exceptionWasThrown);
        }
    }
}

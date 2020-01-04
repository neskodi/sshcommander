<?php

namespace Neskodi\SSHCommander\Tests;

use RuntimeException;

class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        $this->buildSshOptions();

        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            // we can't test anything without a working connection
            $this->markTestSkipped($e->getMessage());
        }
    }
}

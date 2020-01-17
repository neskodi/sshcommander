<?php

namespace Neskodi\SSHCommander\Tests;

use RuntimeException;

class IntegrationTestCase extends TestCase
{
    protected function hasAuthCredentials(): bool
    {
        try {
            $this->requireUser();
            $this->requireAuthCredential();
        } catch (RuntimeException $e) {
            return false;
        }

        return true;
    }

    protected function setUp(): void
    {
        $this->buildSshOptions();

        if (!$this->hasAuthCredentials()) {
            $this->markTestSkipped(
                'Authentication credentials required to run integration tests.'
            );
        }
    }
}

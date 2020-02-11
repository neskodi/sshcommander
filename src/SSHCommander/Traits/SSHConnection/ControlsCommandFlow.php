<?php /** @noinspection PhpUnused */

namespace Neskodi\SSHCommander\Traits\SSHConnection;

use Neskodi\SSHCommander\SSHConfig;

trait ControlsCommandFlow
{
    abstract public function write(string $chars);

    abstract public function writeAndSend(string $chars);

    /**
     * Send the terminate signal (CTRL+C) to the shell.
     */
    public function terminateCommand(): void
    {
        $this->write(SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE);
    }

    /**
     * Send the 'suspend in background' signal (CTRL+Z) to the shell.
     */
    public function suspendCommand(): void
    {
        $this->write(SSHConfig::TIMEOUT_BEHAVIOR_SUSPEND);
    }

    public function continueCommandInBackground(): void
    {
        $this->writeAndSend(SSHConfig::TIMEOUT_BEHAVIOR_CONTINUE_IN_BACKGROUND);
    }
}

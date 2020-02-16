<?php

namespace Neskodi\SSHCommander\CommandRunners\Decorators;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;

class CRPromptDetectionDecorator
    extends CRBaseDecorator
    implements DecoratedCommandRunnerInterface
{
    /**
     * If the command runner defines any error handling behavior, execute it.
     * E.g. set up any error traps, run the command, and reset the traps
     * afterwards.
     *
     * @param SSHCommandInterface $command
     */
    public function execDecorated(SSHCommandInterface $command): void
    {
        // either we are looking for prompt or for markers
        $command->detectsPrompt(!$this->usesMarkers());

        $this->runner->execDecorated($command);
    }
}

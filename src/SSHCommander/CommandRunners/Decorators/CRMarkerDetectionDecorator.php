<?php

namespace Neskodi\SSHCommander\CommandRunners\Decorators;

use Neskodi\SSHCommander\Interfaces\DecoratedCommandRunnerInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;

class CRMarkerDetectionDecorator
    extends CRBaseDecorator
    implements DecoratedCommandRunnerInterface
{
    const MARKER_TYPE_EXIT  = 'exit';
    const MARKER_TYPE_ERROR = 'error';

    /**
     * If the command runner defines any error handling behavior, execute it.
     * E.g. set up any error traps, run the command, and reset the traps
     * afterwards.
     *
     * @param SSHCommandInterface $command
     */
    public function execDecorated(SSHCommandInterface $command): void
    {
        if ($this->usesMarkers()) {
            $this->resetMarkers();
            $this->createMarkers([
                self::MARKER_TYPE_EXIT,
                self::MARKER_TYPE_ERROR,
            ]);
            $this->appendCommandEndMarkerEcho($command);
            $this->enableMarkerDetection($command);
        }

        $this->runner->execDecorated($command);
    }

    /**
     * Append the command echoing the end marker to the end of user's command.
     *
     * @param SSHCommandInterface $command
     */
    protected function appendCommandEndMarkerEcho(
        SSHCommandInterface $command
    ): void {
        // make shell display the last command exit code and the marker
        $append = $this->getMarkerEchoCommand(self::MARKER_TYPE_EXIT);

        // append to command
        $command->appendCommand($append);
    }

    /**
     * Add a hook to command read cycle that will detect the marker in the
     * command output and stop reading.
     *
     * @param SSHCommandInterface $command
     *
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpInconsistentReturnPointsInspection
     */
    protected function enableMarkerDetection(SSHCommandInterface $command): void
    {
        $regex = $this->getMarkerDetectionRegex();

        $command->addReadCycleHook(
            function ($conn, $newOutput) use ($regex) {
                if (
                    $this->markersAreEnabled() &&
                    preg_match($regex, $newOutput)
                ) {
                    // Marker was detected. We must stop reading.
                    return true;
                }
            }
        );
    }
}

<?php

namespace Neskodi\SSHCommander\Traits;

trait UsesMarkers
{
    protected $markers = [];

    /**
     * Create all markers for specified cases in one call.
     *
     * @param array $types
     */
    public function createMarkers(array $types): void
    {
        foreach ($types as $type) {
            $this->createMarker($type);
        }
    }

    /**
     * Generate a marker for the specified case and place it on the markers array.
     *
     * @param string $type
     */
    public function createMarker(string $type): void
    {
        // This operation is idempotent. Even if decorators ask to create the
        // same marker multiple times, it must stay the same as created first
        // time.
        if (!array_key_exists($type, $this->markers)) {
            $this->markers[$type] = uniqid();
        }
    }

    /**
     * Reset the markers after command run.
     */
    public function resetMarkers()
    {
        $this->markers = [];
    }

    /**
     * Read a line of output and see if it contains a marker with an exit code,
     * if so, return them.
     *
     * @param string $line
     *
     * @return null|array
     */
    public function readMarker(string $line): ?array
    {
        $regex   = $this->getMarkerDetectionRegex();
        $matches = [];

        preg_match_all($regex, $line, $matches);

        if (!empty($matches[0])) {
            return [
                'code'   => $matches['CODE'][0],
                'marker' => $matches['MARKER'][0],
                'type'   => array_search($matches['MARKER'][0], $this->markers),
            ];
        }

        return null;
    }

    /**
     * Get the regular expression used to detect any of our markers in the output
     * produced by the command.
     *
     * @return string|null
     */
    public function getMarkerDetectionRegex(): ?string
    {
        return sprintf(
            '/(?<CODE>\d+):(?<MARKER>%s)/',
            implode('|', $this->markers)
        );
    }

    /**
     * Get the shell command that will output our marker together with the exit
     * code of the command.
     *
     * @param string $type
     *
     * @return string
     */
    public function getMarkerEchoCommand(string $type): string
    {
        return sprintf('echo "$?:%s"', $this->markers[$type]);
    }

    /**
     * Enable other classes (e.g. decorators) to quicky detect if runner supports
     * markers.
     *
     * @return bool
     */
    public function usesMarkers(): bool
    {
        return true;
    }

    /**
     * See if at least one marker was defined.
     *
     * @return bool
     */
    public function markersAreEnabled(): bool
    {
        return !empty($this->markers);
    }
}

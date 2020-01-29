<?php

namespace Neskodi\SSHCommander\Traits\SSHConnection;

use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\SSHCommand;
use Neskodi\SSHCommander\Utils;

/**
 * Inspects SSH connection for supported features and functions
 */
trait ExaminesFeatureSupport
{
    // features that will be tested. The result of each test will be put into
    // the same array (true if feature is supported, false otherwise).
    // Initially each feature maps to null, which means 'unknown'.
    protected $connectionFeatures = [
        'system_timeout' => null,
    ];

    // functions this trait needs during horizontal composition
    abstract public function read(): string;

    abstract public function writeAndSend(string $chars);

    abstract public function authenticateIfNecessary(): void;

    abstract public function getConfig(?string $param = null);

    abstract public function cleanCommandOutput(
        string $output,
        SSHCommandInterface $command
    ): string;
    // end dependency declarations

    /**
     * Run the inspections listed in the $connectionFeatures property.
     */
    public function examine(): void
    {
        $this->authenticateIfNecessary();

        foreach ($this->connectionFeatures as $feature => $result) {
            if (empty($feature)) {
                continue;
            }

            $method = 'examine' . Utils::camelCase($feature);
            if (method_exists($this, $method)) {
                $this->$method();
            }
        }
    }

    /**
     * Tell if an examination has been attempted. At least one feature should be
     * either true or false, but not null.
     *
     * @return bool
     * @noinspection PhpUnused
     */
    public function isExamined(): bool
    {
        foreach ($this->connectionFeatures as $feature => $result) {
            if (!is_null($result)) {
                return true;
            }
        }

        return false;
    }

    /** @noinspection PhpUnused */
    public function examineSystemTimeout()
    {
        $timeout = $this->which('timeout');

        $this->connectionFeatures['system_timeout'] = (bool)$timeout;
    }

    /**
     * Run the 'which' command and return the output.
     *
     * @param string $program
     *
     * @return string
     */
    public function which(string $program): string
    {
        $command = "which $program";

        $this->writeAndSend("$command\n");

        $result = $this->read();

        return $this->cleanCommandOutput(
            $result,
            new SSHCommand($command, $this->getConfig())
        );
    }

    /**
     * Check if we have detected support for the given feature during a previous
     * examination.
     *
     * @param string $feature
     *
     * @return bool
     */
    public function supports(string $feature): bool
    {
        return $this->connectionFeatures[$feature] ?? false;
    }
}

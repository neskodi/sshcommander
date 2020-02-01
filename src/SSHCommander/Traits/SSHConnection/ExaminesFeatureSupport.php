<?php

namespace Neskodi\SSHCommander\Traits\SSHConnection;

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

    protected $isExamined = false;

    // functions this trait needs during horizontal composition
    abstract public function read(): string;

    abstract public function writeAndSend(string $chars);

    abstract public function authenticateIfNecessary(): void;

    abstract public function getConfig(?string $param = null);

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

        // calls to exec() may leave artifacts in the channel, so let's clean up
        $this->cleanCommandBuffer();
        $this->isExamined = true;
    }

    /**
     * Tell if an examination has been attempted.
     *
     * @return bool
     * @noinspection PhpUnused
     */
    public function isExamined(): bool
    {
        return $this->isExamined;
    }

    /** @noinspection PhpUnused */
    public function examineSystemTimeout()
    {
        $timeout = $this->getSSH2()->exec('which timeout');

        $this->connectionFeatures['system_timeout'] = (bool)$timeout;
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

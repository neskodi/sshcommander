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

    abstract public function read(): string;

    abstract public function write(string $chars);

    abstract public function authenticateIfNecessary(): void;

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
        $this->write("which timeout\n");

        $return = $this->read();

        $this->connectionFeatures['system_timeout'] = (false !== strpos($return, 'timeout'));
    }

    public function supports(string $feature): bool
    {
        return $this->connectionFeatures[$feature] ?? false;
    }
}

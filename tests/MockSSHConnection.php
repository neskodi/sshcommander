<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Neskodi\SSHCommander\Tests;

use Neskodi\SSHCommander\Interfaces\SSHConnectionInterface;
use Neskodi\SSHCommander\Interfaces\ConfigAwareInterface;
use Neskodi\SSHCommander\Interfaces\LoggerAwareInterface;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use Neskodi\SSHCommander\Interfaces\TimerInterface;
use Neskodi\SSHCommander\SSHConnection;

class MockSSHConnection extends SSHConnection implements
    SSHConnectionInterface,
    LoggerAwareInterface,
    ConfigAwareInterface,
    TimerInterface
{
    const RESULT_SUCCESS = 'ok';
    const RESULT_ERROR = 'error';

    const LINES_STDOUT = [
        'test line 1',
        'test line 2',
        'test line 3',
    ];

    const LINES_STDERR = [
        'test error line 1',
        'test error line 2',
        'test error line 3',
    ];

    protected static $expectedResult = self::RESULT_SUCCESS;

    public static function expect(string $resultType): void
    {
        static::$expectedResult = (self::RESULT_ERROR === $resultType)
            ? self::RESULT_ERROR
            : self::RESULT_SUCCESS;
    }

    public static function expects(?string $resultType = null)
    {
        if (is_null($resultType)) {
            return static::$expectedResult;
        }

        return static::$expectedResult === $resultType;
    }

    public function authenticate(): bool
    {
        $result = static::expects(self::RESULT_ERROR)
            ? false
            : true;

        $this->authenticated = $result;

        return $result;
    }

    protected function run(SSHCommandInterface $command): void
    {
        $this->setCommandTimeout();

        // clean all data from previous commands
        $this->resetOutput();

        $this->logCommandStart($command);
        $this->startTimer();

        usleep(rand(100000, 200000));
        $this->populateRunInfo($command);

        $this->logCommandEnd($this->endTimer());

        $this->resetTimeout();
    }

    protected function populateRunInfo(SSHCommandInterface $command): void
    {
        $this->lastExitCode = static::expects(self::RESULT_ERROR)
            ? 255
            : 0;

        $this->stdoutLines = static::LINES_STDOUT;

        if (static::expects(self::RESULT_ERROR)) {
            if ($command->getConfig('separate_stderr')) {
                $this->stderrLines   = static::LINES_STDERR;
                $this->stderrLines[] = '';
            } else {
                array_push($this->stdoutLines, ...static::LINES_STDERR);
            }
        }

        $this->stdoutLines[] = '';
    }
}

<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Neskodi\SSHCommander\Tests\Mocks;

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

    protected $marker = null;

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

    protected function sshLogin(string $username, $credential): bool
    {
        $result = static::expects(self::RESULT_ERROR)
            ? false
            : true;

        $this->authenticated = $result;

        return $result;
    }

    protected function sshExec(SSHCommandInterface $command): void
    {
        usleep(rand(100000, 200000));
        $this->populateOutput();
        $this->populateStdErrIfNecessary($command);
        $this->setExitCode();
    }

    protected function sshRead(string $chars, int $mode)
    {
        if (!$this->marker) {
            return '';
        }

        $exitCode = static::expects(self::RESULT_ERROR) ? 1 : 0;

        return sprintf('%s:%s', $exitCode, $this->marker);
    }

    protected function sshWrite(string $chars)
    {
        // detect the marker
        $matches = [];
        preg_match_all('/echo "\\$\\?:([^"]+)"/', $chars, $matches);

        if (!empty($matches[0])) {
            $this->marker = $matches[1][0];
        }
    }

    protected function setExitCode()
    {
        $this->lastExitCode = static::expects(self::RESULT_ERROR)
            ? 255
            : 0;
    }

    protected function populateStdErrIfNecessary(SSHCommandInterface $command)
    {
        if (static::expects(self::RESULT_ERROR)) {
            if ($command->getConfig('separate_stderr')) {
                $this->populateStderr();
            } else {
                array_push($this->stdoutLines, ...static::LINES_STDERR);
            }
        }
    }

    protected function populateStderr(): void
    {
        $this->stderrLines   = static::LINES_STDERR;
        $this->stderrLines[] = '';
    }

    protected function populateOutput(): void
    {
        $this->stdoutLines = static::LINES_STDOUT;
        $this->stdoutLines[] = '';
    }
}

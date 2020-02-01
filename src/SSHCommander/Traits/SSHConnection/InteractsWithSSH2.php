<?php

namespace Neskodi\SSHCommander\Traits\SSHConnection;

use Neskodi\SSHCommander\Exceptions\CommandRunException;
use Neskodi\SSHCommander\Interfaces\SSHCommandInterface;
use phpseclib\Net\SSH2;

/**
 * Trait InteractsWithSSH2
 *
 * Contains wrappers around phpseclib's read(), write(), and exec() methods.
 */
trait InteractsWithSSH2
{
    abstract public function getSSH2(): SSH2;

    abstract public function processOutput(SSHCommandInterface $command, string $output): array;

    /**
     * Phpseclib throws errors using user_error(). We will intercept this by
     * using our own error handler function that will throw a
     * CommandRunException.
     *
     * @param $errno
     * @param $errstr
     *
     * @throws CommandRunException
     */
    public function handleSSH2Error($errno, $errstr)
    {
        throw new CommandRunException("$errno:$errstr");
    }

    /**
     * Tell phpseclib to write the characters to the channel. Handle errors
     * thrown by phpseclib on our side.
     *
     * @param string $chars
     *
     * @return bool
     */
    protected function sshWrite(string $chars)
    {
        set_error_handler([$this, 'handleSSH2Error']);

        $result = $this->getSSH2()->write($chars);

        restore_error_handler();

        return $result;
    }

    /**
     * Tell phpseclib to read packets from the channel and pass through the
     * result.
     *
     * @param string $chars
     * @param int    $mode
     *
     * @return bool|string
     */
    protected function sshRead(string $chars, int $mode)
    {
        set_error_handler([$this, 'handleSSH2Error']);

        $result = $this->getSSH2()->read($chars, $mode);

        restore_error_handler();

        return $result;
    }

    /**
     * Execute the command via phpseclib, possibly applying the requested
     * callback to each piece of output.
     *
     * @param string        $command
     * @param callable|null $function
     */
    protected function sshExec(string $command, ?callable $function = null): void
    {
        set_error_handler([$this, 'handleSSH2Error']);

        $this->getSSH2()->exec($command, $function);

        restore_error_handler();
    }
}

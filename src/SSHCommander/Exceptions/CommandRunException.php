<?php

namespace Neskodi\SSHCommander\Exceptions;

use Exception;
use Throwable;

class CommandRunException extends Exception
{
    public function __construct(string $message = '', int $code = 0, Throwable $previous = null)
    {
        if (empty($message)) {
            $message = 'Command returned a non-zero exit code.';
        }

        parent::__construct($message, $code, $previous);
    }
}

<?php

namespace Neskodi\SSHCommander\Exceptions;

use InvalidArgumentException;
use Throwable;

class EmptyCommandException extends InvalidArgumentException
{
    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        if (empty($message)) {
            $message = 'Empty command string was provided';
        }

        parent::__construct($message, $code, $previous);
    }
}

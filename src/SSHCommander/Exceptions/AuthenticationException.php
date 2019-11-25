<?php

namespace Neskodi\SSHCommander\Exceptions;

use Exception;
use Throwable;

class AuthenticationException extends Exception
{
    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        if (empty($message)) {
            $message = 'Failed to authenticate to remote host.';
        }

        parent::__construct($message, $code, $previous);
    }
}

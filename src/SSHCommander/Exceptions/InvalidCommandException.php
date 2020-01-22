<?php

namespace Neskodi\SSHCommander\Exceptions;

use InvalidArgumentException;
use Throwable;

class InvalidCommandException extends InvalidArgumentException
{
    public function __construct(
        string $vartype = '<unknown>',
        int $code = 0,
        Throwable $previous = null
    ) {
        $message = 'Command must be either an array, a string or a Command ' .
                   'object, %s given';

        $message = sprintf($message, $vartype);

        parent::__construct($message, $code, $previous);
    }
}

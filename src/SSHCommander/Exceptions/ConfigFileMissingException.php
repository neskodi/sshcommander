<?php

namespace Neskodi\SSHCommander\Exceptions;

use RuntimeException;
use Throwable;

class ConfigFileMissingException extends RuntimeException
{
    public function __construct(
        $file = '',
        $code = 0,
        Throwable $previous = null
    ) {
        $message = sprintf(
            'Required configuration file "%s" is missing',
            $file
        );

        parent::__construct($message, $code, $previous);
    }
}

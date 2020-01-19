<?php

namespace Neskodi\SSHCommander\Exceptions;

use InvalidArgumentException;
use Throwable;

class InvalidConfigException extends InvalidArgumentException
{
    public function __construct(
        string $vartype = '<unknown>',
        int $code = 0,
        Throwable $previous = null
    ) {
        $message = 'Config passed to SSHCommander must be either an array ' .
                   'or an instance of SSHCommanderConfig, %s given';

        $message = sprintf($message, $vartype);

        parent::__construct($message, $code, $previous);
    }
}

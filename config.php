<?php

return [
    // SSH port
    'port' => 22,

    // Addresses that are considered local. For these addresses, SSH connection
    // is not established, instead commands are run via "exec" directly on
    // command line.
    'local_addresses' => ['127.0.0.1', 'localhost'],

    // Whether to login automatically after establishing an SSH connection.
    'autologin' => true,

    // Move into this directory right after login. Leave null to skip.
    'basedir' => null,

    // If you run multiple commands and want to stop the sequence if any of them
    // returns an error code, set this to true
    'break_on_error' => false,

    // Timeout for establishing TCP connection to remote host.
    'timeout_connect' => 10,

    // How long to wait for a command to complete.
    'timeout_command' => 120,

    // Character or string used to separate commands if multiple commands are
    // passed as one line.
    'delimiter_split_input' => PHP_EOL,

    // Character or string used to glue multiple commands when passed to SSH2
    // for execution. Don't change unless you know what you are doing.
    'delimiter_join_input' => PHP_EOL,

    // Character or string used to split multiple lines of output returned by
    // a command.
    'delimiter_split_output' => "\n",

    // Character or string used to glue together multiple lines of output
    // before returning to your program
    'delimiter_join_output' => PHP_EOL,

    // Whether to return stderr command output separately from stdout.
    'separate_stderr' => false,

    // Whether to include stderr in command result in normal mode.
    'suppress_stderr' => false,
];

<?php

return [
    // SSH port
    'port' => 22,

    // Whether to login automatically after establishing an SSH connection.
    'autologin' => true,

    // Move into this directory right after login. Leave null to skip.
    'basedir' => null,

    // Stop running a sequence of commands if any of them returns an error code
    'break_on_error' => true,

    // Timeout for establishing TCP connection to remote host.
    'timeout_connect' => 10,

    // Timeout for commands
    'timeout' => 10,

    // 'runtime' => the command will timeout if it's in general running longer
    // than the specified value, regardless of whether it is receiving packets
    // from the other side or not.
    // 'noout' => the command will not timeout until packets stop coming from
    // the other side and this silence continues for the specified number of
    // seconds.
    'timeout_condition' => 'runtime',

    // If non-null, this character or sequence will be sent to the SSH channel
    // after a timeout is detected. For instance, you may use "\x03" to send
    // CTRL+C after a timeout. This will cancel the old command and make sure
    // that its late output does not interfere with subsequent commands.
    // "\x03" or SSHConfig::TIMEOUT_BEHAVIOR_TERMINATE - send CTRL+C
    // "\x1A" or SSHConfig::TIMEOUT_BEHAVIOR_SUSPEND - send CTRL+Z
    // null - do not handle timeout in any special way
    // provide a callable function ($command) { ... } to define your own behavior
    'timeout_behavior' => "\x03",

    // Character or string used to glue multiple commands when passed to SSH2
    // for execution. Don't change unless you know what you are doing.
    'delimiter_join_input' => "\n",

    // Character or string used to split multiple lines of output returned by
    // a command. Default is to split by any sequence of \r and \n characters.
    'delimiter_split_output_regex' => '/[\r\n]+/',

    // Character or string used to split multiple lines of output returned by
    // a command. Only used if 'delimiter_split_output_regex' is not specified
    // (set to null).
    'delimiter_split_output' => "\n",

    // Character or string used to glue together multiple lines of output
    // before returning to your program
    'delimiter_join_output' => PHP_EOL,

    // Whether to return stderr command output separately from stdout.
    'separate_stderr' => false,

    // Whether to include stderr in command result in normal mode.
    'suppress_stderr' => false,

    // Provide a path to a writeable file to enable logging.
    'log_file' => null,

    // 'error' (only connection errors and timeouts)
    // 'notice' (connection errors, timeouts, and commands that return an error exit code)
    // 'info' (report about completing basic operations, such as connect, send command, get response, disconnect)
    // 'debug' (includes the entire command output in the log)
    'log_level' => 'info',

    // trim the last line of output if it is empty, which is almost always true
    'output_trim_last_empty_line' => true,

    // regular expression used to detect command prompt
    'prompt_regex' => '/[^@\s]+@[^:]+:.*[$%#>]\s?$/',

    // if you disable this, interactive SSH sequence won't run an extra
    // "echo $?" after each command to find out its exit code, but it also means
    // that exit codes won't be available in command results.
    'disable_exit_code_check' => false,
];

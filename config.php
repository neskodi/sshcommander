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

    // How long to wait for a command to complete.
    'timeout_command' => 10,

    // Character or string used to separate commands if multiple commands are
    // passed as one line.
    'delimiter_split_input' => PHP_EOL,

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

    // if force_timeout is false, ssh connection won't time out as long as it
    // is receiving new packets from the other side. For example, if our
    // command is "ping google.com" and our timeout is 10 seconds, in fact the
    // command will never time out because it keeps receiving packets every
    // second or so.
    // Set 'force_timeout' to true to force return control to your program after
    // timeout_command elapses, regardless of whether new data is coming or not
    // in the SSH channel. Note that due to SSH2 implementation peculiarities,
    // this still doesn't ALWAYS work, the "sleep" command being a notable
    // exception, since it will always retain SSH session for whatever number of
    // seconds it's told to sleep.
    'force_timeout' => false,

    // if you disable this, interactive SSH sequence won't run an extra
    // "echo $?" after each command to find out exit code, but it also won't be
    // available in command result.
    'disable_exit_code_check' => false,
];

<?php

// these values are only used to test the ability to override config values.
// they are not used in real SSH connection and command tests.
return [
    'host'     => '127.0.0.1',
    'port'     => '2222',
    'user'     => 'foo',
    'password' => 'bar',
    'key'      => 'ssh-rsa secret',
    'keyfile'  => __FILE__,

    'local_addresses'             => ['127.0.0.1', '::1'],
    'autologin'                   => false,
    'break_on_error'              => false,
    'basedir'                     => '/tmp',
    'timeout_connect'             => 2,
    'timeout_command'             => 4,
    'delimiter_split_input'       => ';',
    'delimiter_split_output'      => ';',
    'delimiter_join_input'        => ';',
    'delimiter_join_output'       => ';',
    'separate_stderr'             => true,
    'suppress_stderr'             => true,
    'log_file'                    => '/tmp/sshcommanderlog.txt',
    'log_level'                   => 'debug',
    'output_trim_last_empty_line' => true,
];

<?php /** @noinspection PhpIncludeInspection */

// these values are only used to test the ability to override config values.
// they are not used in real SSH connection and command tests.
use Neskodi\SSHCommander\Tests\TestCase;

return [
    'host'     => '127.0.0.1',
    'port'     => '2222',
    'user'     => 'foo',
    'password' => 'bar',
    'key'      => '---RSA PRIVATE KEY---',
    'keyfile'  => TestCase::getKeyPath('testkey'),

    'autologin'                    => false,
    'break_on_error'               => false,
    'basedir'                      => '/tmp',
    'timeout_connect'              => 2,
    'timeout'                      => 4,
    'timeout_condition'            => 'noout',
    'timeout_behavior'             => "\x03",
    'delimiter_split_output_regex' => '/[\r\n]+/',
    'delimiter_split_output'       => "\n",
    'delimiter_join_input'         => ';',
    'delimiter_join_output'        => PHP_EOL,
    'separate_stderr'              => true,
    'suppress_stderr'              => true,
    'log_file'                     => null,
    'log_level'                    => 'debug',
    'output_trim_last_empty_line'  => true,
    'prompt_regex'                 => '/[^@\s]+@[^:]+:.*[$%#>]\s?$/',
    'force_timeout'                => false,
    'disable_exit_code_check'      => false,
];

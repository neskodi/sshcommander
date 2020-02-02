<?php

namespace Neskodi\SSHCommander;

class Utils
{
    /**
     * Convert a camel-cased string to underscore-separated, e.g.
     * TimeoutConnect => timeout_connect
     *
     * @param string $string
     *
     * @return string
     */
    public static function snakeCase(string $string): string
    {
        $arr = preg_split('/(?!\b)(?=[A-Z])/', $string);

        return implode('_', array_map('lcfirst', $arr));
    }

    /**
     * Convert an underscore-delimited string to camelCase,
     * e.g. connect_timeout => ConnectTimeout
     *
     * @param string $string
     *
     * @return string
     */
    public static function camelCase(string $string): string
    {
        $arr = explode('_', $string);

        return implode('', array_map('ucfirst', $arr));
    }

    /**
     * See if the filename provided is an existing writable local file,
     * or if not, if it will be possible to create it.
     *
     * @param $file
     *
     * @return bool
     */
    public static function isWritableOrCreatable($file): bool
    {
        return (
            (!file_exists($file) && is_writable(dirname($file))) ||
            is_writable($file)
        );
    }

    /**
     * Given a string, return it squashed into single line by replacing all
     * newline characters with their escaped representations (i.e. \r and \n).
     * In particular,this is useful for logging.
     *
     * @param string $string
     *
     * @return string
     */
    public static function oneLine(string $string): string
    {
        return str_replace(["\r", "\n"], ['\r', '\n'], $string);
    }

    /**
     * See if the character user wants to send is a control (non-printable)
     * character such as CTRL+C or CTRL+Z
     *
     * For our purposes, let's just consider everything below ASCII code 32 a
     * control character.
     *
     * @param string $char
     *
     * @return bool
     */
    public static function isAsciiControlCharacter(string $char): bool
    {
        return (1 === strlen($char)) && (32 > ord($char));
    }
}

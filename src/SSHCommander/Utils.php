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

    public static function isWritableOrCreatable($file): bool
    {
        return (
            (!file_exists($file) && is_writable(dirname($file))) ||
            is_writable($file)
        );
    }

    public static function oneLine(string $string): string
    {
        return str_replace(["\r", "\n"], ['\r', '\n'], $string);
    }
}

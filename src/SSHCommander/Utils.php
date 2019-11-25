<?php

namespace Neskodi\SSHCommander;

class Utils
{
    /**
     * Convert a camel-cased string to underscore-separated, e.g.
     * LocalAddresses => local_addresses
     *
     * @param $string
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
     * e.g. local_addresses => LocalAddresses
     *
     * @param $string
     *
     * @return string
     */
    public static function camelCase(string $string): string
    {
        $arr = explode('_', $string);

        return implode('', array_map('ucfirst', $arr));
    }
}

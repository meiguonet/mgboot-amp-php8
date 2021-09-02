<?php

namespace mgboot\util;

use Throwable;

final class SerializeUtils
{
    private function __construct()
    {
    }

    public static function serialize(mixed $arg0): string
    {
        if (extension_loaded('igbinary')) {
            try {
                $contents = igbinary_serialize($arg0);
            } catch (Throwable) {
                $contents = '';
            }

            return "igb:$contents";
        }

        try {
            $contents = serialize($arg0);
        } catch (Throwable) {
            $contents = '';
        }

        return "php:$contents";
    }

    public static function unserialize(string $contents): mixed
    {
        if (str_starts_with($contents, 'igb:')) {
            if (!extension_loaded('igbinary')) {
                return null;
            }

            $contents = StringUtils::substringAfter($contents, ':');

            try {
                return igbinary_unserialize($contents);
            } catch (Throwable) {
                return null;
            }
        }

        $contents = StringUtils::substringAfter($contents, ':');

        try {
            return unserialize($contents);
        } catch (Throwable) {
            return null;
        }
    }
}

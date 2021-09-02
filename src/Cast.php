<?php

namespace mgboot;

use mgboot\util\ArrayUtils;
use mgboot\util\StringUtils;

final class Cast
{
    private function __construct()
    {
    }

    public static function toInt(mixed $arg0, int $default = PHP_INT_MIN): int
    {
        if (is_int($arg0)) {
            return $arg0;
        }

        if (is_float($arg0)) {
            return (int)$arg0;
        }

        if (is_string($arg0) && StringUtils::isInt($arg0)) {
            return (int)$arg0;
        }

        return $default;
    }

    public static function toFloat(mixed $arg0, float $default = PHP_FLOAT_MIN): float
    {
        if (is_float($arg0)) {
            return $arg0;
        }

        if (is_int($arg0)) {
            return (float)$arg0;
        }

        if (is_string($arg0) && StringUtils::isFloat($arg0)) {
            return (float)$arg0;
        }

        return $default;
    }

    public static function toString(mixed $arg0, string $default = ''): string
    {
        if (is_string($arg0)) {
            return $arg0;
        }

        if (is_int($arg0) || is_float($arg0)) {
            return "$arg0";
        }

        if (is_bool($arg0)) {
            return $arg0 ? 'true' : 'false';
        }

        if (is_object($arg0) && method_exists($arg0, '__toString')) {
            $result = call_user_func([$arg0, '__toString']);
            return is_string($result) ? $result : $default;
        }

        return $default;
    }

    public static function toBoolean(mixed $arg0, bool $default = false): bool
    {
        if (is_bool($arg0)) {
            return $arg0;
        }

        if (is_int($arg0) && in_array($arg0, [1, 0])) {
            return $arg0 === 1;
        }

        if (is_string($arg0)) {
            $arg0 = strtolower($arg0);

            if (in_array($arg0, ['true', 'false'])) {
                return $arg0 === 'true';
            }

            if (in_array($arg0, ['1', '0'])) {
                return $arg0 === '1';
            }
        }

        return $default;
    }

    public static function toAssocArray(mixed $arg0): array
    {
        if (!is_array($arg0)) {
            return [];
        }

        $ret = [];

        foreach ($arg0 as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $ret[$key] = $value;
        }

        return $ret;
    }

    public static function toIntArray(mixed $arg0): array
    {
        if (!is_array($arg0)) {
            return [];
        }

        $ret = [];

        foreach ($arg0 as $value) {
            $value = self::toInt($value);

            if ($value === PHP_INT_MIN) {
                continue;
            }

            $ret[] = $value;
        }

        return $ret;
    }

    public static function toStringArray(mixed $arg0): array
    {
        if (empty($arg0) || !ArrayUtils::isList($arg0)) {
            return [];
        }

        $ret = [];
        $s1 = '@~null~@';

        foreach ($arg0 as $value) {
            $value = self::toString($value, $s1);

            if ($value === $s1) {
                continue;
            }

            $ret[] = $value;
        }

        return $ret;
    }

    public static function toMapList(mixed $arg0): array
    {
        if (empty($arg0) || !ArrayUtils::isList($arg0)) {
            return [];
        }

        $ret = [];

        foreach ($arg0 as $value) {
            if (!is_array($value)) {
                continue;
            }

            $keys = array_keys($value);
            $isAllStringKey = true;

            foreach ($keys as $key) {
                if (!is_string($key) || $key === '') {
                    $isAllStringKey = false;
                    break;
                }
            }

            if ($isAllStringKey) {
                $ret[] = $value;
            }
        }

        return $ret;
    }

    public static function toDuration(mixed $arg0): int
    {
        return StringUtils::toDuration(self::toString($arg0));
    }

    public static function toDataSize(mixed $arg0): int
    {
        return StringUtils::toDataSize(self::toString($arg0));
    }
}

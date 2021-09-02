<?php

namespace mgboot\util;

use mgboot\Cast;
use mgboot\constant\Regexp;
use mgboot\constant\RequestParamSecurityMode as SecurityMode;
use mgboot\HtmlPurifier;

final class ArrayUtils
{
    private function __construct()
    {
    }

    public static function first(array $arr, callable $callback): mixed
    {
        if (empty($arr) || !self::isList($arr)) {
            return null;
        }

        return collect($arr)->first($callback);
    }

    public static function camelCaseKeys(array $arr): array
    {
        if (empty($arr)) {
            return [];
        }

        foreach ($arr as $key => $value) {
            if (!is_string($key)) {
                unset($arr[$key]);
                continue;
            }

            $newKey = $key;
            $needUcwords = false;

            if (str_contains($newKey, '-')) {
                $newKey = str_replace('-', ' ', $newKey);
                $needUcwords = true;
            } else if (str_contains($newKey, '_')) {
                $newKey = str_replace('_', ' ', $newKey);
                $needUcwords = true;
            }

            if ($needUcwords) {
                $newKey = str_replace(' ', '', ucwords($newKey));
            }

            if ($newKey === $key) {
                continue;
            }

            $arr[$newKey] = $value;
            unset($key);
        }

        return $arr;
    }

    public static function removeKeys(array $arr, string|array $keys): array
    {
        if (is_string($keys) && $keys !== '') {
            $keys = preg_split('/[\x20\t]*,[\x20\t]*/', $keys);
        }

        if (!is_array($keys) || empty($keys)) {
            return $arr;
        }

        if (!self::isAssocArray($arr)) {
            foreach ($arr as $key => $val) {
                $arr[$key] = self::removeKeys($val, $keys);
            }

            return $arr;
        }

        foreach ($arr as $key => $val) {
            if (!is_string($key) || !in_array($key, $keys)) {
                continue;
            }

            unset($arr[$key]);
        }

        return $arr;
    }

    public static function removeEmptyFields(array $arr): array
    {
        if (empty($arr)) {
            return [];
        }

        foreach ($arr as $key => $value) {
            if ($value === null) {
                unset($arr[$key]);
                continue;
            }

            if ($value === '') {
                unset($arr[$key]);
            }
        }

        return $arr;
    }

    public static function isAssocArray(mixed $arg0): bool
    {
        if (!is_array($arg0) || empty($arg0)) {
            return false;
        }

        $keys = array_keys($arg0);

        foreach ($keys as $key) {
            if (!is_string($key)) {
                return false;
            }
        }

        return true;
    }

    public static function isList(mixed $arg0): bool
    {
        if (!is_array($arg0) || empty($arg0)) {
            return false;
        }

        $keys = array_keys($arg0);
        $n1 = count($keys);

        for ($i = 0; $i < $n1; $i++) {
            if (!is_int($keys[$i]) || $keys[$i] < 0) {
                return false;
            }

            if ($i > 0 && $keys[$i] - 1 !== $keys[$i - 1]) {
                return false;
            }
        }

        return true;
    }

    public static function isIntArray(mixed $arg0): bool
    {
        if (!self::isList($arg0)) {
            return false;
        }

        foreach ($arg0 as $val) {
            if (!is_int($val)) {
                return false;
            }
        }

        return true;
    }

    public static function isStringArray(mixed $arg0): bool
    {
        if (!self::isList($arg0)) {
            return false;
        }

        foreach ($arg0 as $val) {
            if (!is_string($val)) {
                return false;
            }
        }

        return true;
    }

    public static function toxml(array $arr, array $cdataKeys = []): string
    {
        $sb = [str_replace('/', '', '<xml/>')];

        foreach ($arr as $key => $val) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (is_int($val) || is_numeric($val) || !in_array($key, $cdataKeys)) {
                $sb[] = "<$key>$val</$key>";
            } else {
                $sb[] = "<$key><![CDATA[$val]]></$key>";
            }
        }

        $sb[] = '</xml>';
        return implode('', $sb);
    }

    public static function requestParams(array $arr, array|string $rules): array
    {
        if (is_string($rules) && $rules !== '') {
            $rules = preg_split('/[\x20\t]*,[\x20\t]*/', $rules);
        }
        
        if (!self::isStringArray($rules) || empty($rules)) {
            return $arr;
        }

        $map1 = [];

        foreach ($rules as $rule) {
            $type = 1;
            $securityMode = SecurityMode::STRIP_TAGS;
            $defaultValue = null;

            if (str_starts_with($rule, 'i:')) {
                $type = 2;
                $rule = StringUtils::substringAfter($rule, ':');
            } else if (str_starts_with($rule, 'd:')) {
                $type = 3;
                $rule = StringUtils::substringAfter($rule, ':');
            } else if (str_starts_with($rule, 's:')) {
                $rule = StringUtils::substringAfter($rule, ':');
            } else if (str_starts_with($rule, 'a:')) {
                $type = 4;
                $rule = StringUtils::substringAfter($rule, ':');
            }

            $paramName = '';

            switch ($type) {
                case 1:
                    if (str_ends_with($rule, ':0')) {
                        $paramName = StringUtils::substringBeforeLast($rule, ':');
                        $securityMode = SecurityMode::NONE;
                    } else if (str_ends_with($rule, ':1')) {
                        $paramName = StringUtils::substringBeforeLast($rule, ':');
                        $securityMode = SecurityMode::HTML_PURIFY;
                    } else if (str_ends_with($rule, ':2')) {
                        $paramName = StringUtils::substringBeforeLast($rule, ':');
                    } else {
                        $paramName = $rule;
                    }

                    break;
                case 2:
                    if (str_contains($rule, ':')) {
                        $defaultValue = StringUtils::substringAfterLast($rule, ':');
                        $defaultValue = StringUtils::isInt($defaultValue) ? (int) $defaultValue : PHP_INT_MIN;
                        $paramName = StringUtils::substringBeforeLast($rule, ':');
                    } else {
                        $paramName = $rule;
                    }

                    $defaultValue = is_int($defaultValue) ? $defaultValue : PHP_INT_MIN;
                    break;
                case 3:
                    if (str_contains($rule, ':')) {
                        $defaultValue = StringUtils::substringAfterLast($rule, ':');
                        $defaultValue = StringUtils::isFloat($defaultValue) ? bcadd($defaultValue, 0, 2) : null;
                        $paramName = StringUtils::substringBeforeLast($rule, ':');
                    } else {
                        $paramName = $rule;
                    }

                    $defaultValue = is_string($defaultValue) ? $defaultValue : '0.00';
                    break;
            }

            if (empty($paramName)) {
                continue;
            }

            switch ($type) {
                case 2:
                    $value = Cast::toInt($arr[$paramName], is_int($defaultValue) ? $defaultValue : PHP_INT_MIN);
                    break;
                case 3:
                    $value = Cast::toString($arr[$paramName]);
                    $value = StringUtils::isFloat($value) ? bcadd($value, 0, 2) : $defaultValue;
                    break;
                case 4:
                    $value = json_decode(Cast::toString($arr[$paramName]), true);
                    $value = is_array($value) ? $value : [];
                    break;
                default:
                    $value = self::getStringWithSecurityMode($arr, $paramName, $securityMode);
                    break;
            }

            $map1[$paramName] = $value;
        }

        return $map1;
    }

    public static function copyFields(mixed $arr, array|string $keys): array
    {
        if (is_string($keys) && $keys !== '') {
            $keys = preg_split(Regexp::COMMA_SEP, $keys);
        }

        if (empty($keys) || !self::isStringArray($keys)) {
            return [];
        }

        $map1 = [];

        foreach ($arr as $key => $val) {
            if (!in_array($key, $keys)) {
                continue;
            }

            $map1[$key] = $val;
        }

        return $map1;
    }

    public static function fromBean(mixed $obj, array $propertyNameToMapKey = [], bool $ignoreNull = false): array
    {
        if (is_object($obj) && method_exists($obj, 'toMap')) {
            return $obj->toMap($propertyNameToMapKey, $ignoreNull);
        }

        return [];
    }

    private static function getStringWithSecurityMode(
        array $arr,
        string $key,
        int $securityMode = SecurityMode::STRIP_TAGS
    ): string
    {
        $value = $arr[$key];

        if (is_int($value) || is_float($value)) {
            return "$value";
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (!is_string($value)) {
            return '';
        }

        if ($value === '') {
            return $value;
        }

        return match ($securityMode) {
            SecurityMode::HTML_PURIFY => HtmlPurifier::purify($value),
            SecurityMode::STRIP_TAGS => strip_tags($value),
            default => $value
        };
    }
}

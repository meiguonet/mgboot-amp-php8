<?php

namespace mgboot\util;

final class TokenizeUtils
{
    private function __construct()
    {
    }

    public static function getQualifiedClassName(array $tokens): string
    {
        $namespace = self::getNamespace($tokens);
        $className = self::getSimpleClassName($tokens);

        if (empty($className)) {
            return '';
        }

        if (empty($namespace)) {
            return StringUtils::ensureLeft($className, "\\");
        }

        return StringUtils::ensureLeft($namespace, "\\") . StringUtils::ensureLeft($className, "\\");
    }

    public static function getNamespace(array $tokens): string
    {
        $n1 = -1;

        foreach ($tokens as $token) {
            if (!self::isToken($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $n1 = $token[2];
                break;
            }
        }

        if ($n1 < 0) {
            return '';
        }

        foreach ($tokens as $token) {
            if (!self::isToken($token)) {
                continue;
            }

            if ($token[0] === T_NAME_QUALIFIED && $token[2] === $n1) {
                return $token[1];
            }
        }

        return '';
    }

    public static function getUsedClasses(array $tokens): array
    {
        $nums = [];

        foreach ($tokens as $token) {
            if (!self::isToken($token)) {
                continue;
            }

            if ($token[0] !== T_USE) {
                continue;
            }

            $nums[] = $token[2];
        }

        if (empty($nums)) {
            return [];
        }

        $classes = [];

        foreach ($tokens as $token) {
            if (!self::isToken($token)) {
                continue;
            }

            if ($token[0] !== T_NAME_QUALIFIED || !in_array($token[2], $nums)) {
                continue;
            }

            $classes[] = $token[1];
        }

        return $classes;
    }

    private static function getSimpleClassName(array $tokens): string
    {
        $n1 = -1;

        foreach ($tokens as $token) {
            if (!self::isToken($token)) {
                continue;
            }

            if ($token[0] === T_CLASS) {
                $n1 = $token[2];
                break;
            }
        }

        if ($n1 < 0) {
            return '';
        }

        foreach ($tokens as $token) {
            if (!self::isToken($token)) {
                continue;
            }

            if ($token[0] === T_STRING && $token[2] === $n1) {
                return $token[1];
            }
        }

        return '';
    }

    private static function isToken(mixed $arg0): bool
    {
        return is_array($arg0) && count($arg0) >= 3 && is_int($arg0[0]) && is_string($arg0[1]) && is_int($arg0[2]);
    }
}

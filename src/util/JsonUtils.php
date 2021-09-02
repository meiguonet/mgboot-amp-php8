<?php

namespace mgboot\util;

use stdClass;

final class JsonUtils
{
    private function __construct()
    {
    }

    public static function mapFrom(mixed $arg0): array|stdClass
    {
        if (!is_string($arg0) || empty($arg0)) {
            return new stdClass();
        }

        $data = json_decode($arg0, true);
        return ArrayUtils::isAssocArray($data) ? $data : new stdClass();
    }

    public static function arrayFrom(mixed $arg0): array
    {
        if (!is_string($arg0) || empty($arg0)) {
            return [];
        }

        $data = json_decode($arg0, true);
        return ArrayUtils::isList($data) ? $data : [];
    }

    public static function toJson(mixed $arg0): string {
        $json = json_encode($arg0, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '';
    }

    public static function toJsonObjectString(mixed $arg0): string
    {
        $json = json_encode($arg0, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        if (!is_string($json) || !str_starts_with($json, '{') || !str_ends_with($json, '}')) {
            return '{}';
        }

        return $json;
    }

    public static function toJsonArrayString(mixed $arg0): string
    {
        $json = json_encode($arg0, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        if (!is_string($json) || !str_starts_with($json, '[') || !str_ends_with($json, ']')) {
            return '[]';
        }

        return $json;
    }
}

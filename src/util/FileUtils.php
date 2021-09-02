<?php

namespace mgboot\util;

use Dflydev\ApacheMimeTypes\Parser;

final class FileUtils
{
    private function __construct()
    {
    }

    public static function scanFiles(string $dir, array &$list): void
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            $dir = str_replace("\\", '/', $dir);
        }

        $entries = scandir($dir);

        if (!is_array($entries) || empty($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fpath = "$dir/$entry";

            if (is_dir($fpath)) {
                self::scanFiles($fpath, $list);
                continue;
            }

            array_push($list, $fpath);
        }
    }

    public static function getExtension(string $filepath): string
    {
        if (!str_contains($filepath, '.')) {
            return '';
        }

        return strtolower(StringUtils::substringAfterLast($filepath, '.'));
    }

    public static function getMimeType(string $filepath, bool $strictMode = false): string
    {
        if (!$strictMode) {
            return self::getMimeTypeByExtension(self::getExtension($filepath));
        }

        if (!extension_loaded('fileinfo')) {
            return '';
        }

        if (!is_file($filepath)) {
            return '';
        }

        $finfo = finfo_open(FILEINFO_MIME);

        if ($finfo === false) {
            return '';
        }

        $mimeType = finfo_file($finfo, $filepath);
        finfo_close($finfo);

        if (empty($mimeType)) {
            return '';
        }

        return str_contains($mimeType, ';') ? StringUtils::substringBefore($mimeType, ';') : $mimeType;
    }

    public static function getRealpath(string $path): string
    {
        if (!defined('_ROOT_') || !str_starts_with($path, 'classpath:')) {
            return $path;
        }

        $dir = _ROOT_;

        if (!is_dir($dir)) {
            return $path;
        }

        $path = str_replace('classpath:', '', $path);

        if (empty($path) || $path === '.') {
            return $dir;
        }

        return $dir . StringUtils::ensureLeft($path, '/');
    }

    private static function getMimeTypeByExtension(string $fileExt): string
    {
        if (empty($fileExt)) {
            return '';
        }

        $parser = new Parser();
        $mineTypesFile = __DIR__ . '/mime.types';
        $map1 = $parser->parse($mineTypesFile);

        foreach ($map1 as $mimeType => $extensions) {
            if (in_array($fileExt, $extensions)) {
                return $mimeType;
            }
        }

        return '';
    }
}

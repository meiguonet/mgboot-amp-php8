<?php

namespace mgboot;

use mgboot\util\ArrayUtils;
use mgboot\util\FileUtils;
use mgboot\util\StringUtils;
use Symfony\Component\Yaml\Yaml;
use Throwable;

final class AppConf
{
    private static string $env = 'dev';
    private static bool $cacheEnabled = false;
    private static string $cacheDir = '';
    private static array $data = [];

    public static function setEnv(string $env): void
    {
        defined('_ENV_') && define('_ENV_', $env);
        self::$env = $env;
    }

    public static function getEnv(): string
    {
        return self::$env;
    }
    
    public static function enableCache(string $cacheDir): void
    {
        if (self::$env === 'dev' || $cacheDir === '' || !is_dir($cacheDir) || !is_writable($cacheDir)) {
            return;
        }

        self::$cacheEnabled = true;
        self::$cacheDir = $cacheDir;
    }

    public static function init(): array
    {
        $data = self::getDataFromCache();
        self::$data = $data;
        return $data;
    }

    public static function clearCache(): void
    {
        $dir = self::$cacheDir;
        $cacheFile = "$dir/appconf.php";

        if (!is_file($cacheFile)) {
            return;
        }

        unlink($cacheFile);
    }

    public static function get(string $key): mixed
    {
        if (!str_contains($key, '.')) {
            return self::getValueInternal($key);
        }

        $lastKey = StringUtils::substringAfterLast($key, '.');
        $keys = explode('.', StringUtils::substringBeforeLast($key, '.'));
        $map1 = [];

        foreach ($keys as $i => $key) {
            if ($i === 0) {
                $map1 = self::getValueInternal($key);
                continue;
            }

            if (!is_array($map1) || empty($map1)) {
                break;
            }

            $map1 = self::getValueInternal($key, $map1);
        }

        return self::getValueInternal($lastKey, $map1);
    }

    public static function getAssocArray(string $key): array
    {
        $map1 = self::get($key);
        return ArrayUtils::isAssocArray($map1) ? $map1 : [];
    }

    public static function getInt(string $key, int $defaultValue = PHP_INT_MIN): int
    {
        return Cast::toInt(self::get($key), $defaultValue);
    }

    public static function getFloat(string $key, float $defaultValue = PHP_FLOAT_MIN): float
    {
        return Cast::toFloat(self::get($key), $defaultValue);
    }

    public static function getString(string $key, string $defaultValue = ''): string
    {
        return Cast::toString(self::get($key), $defaultValue);
    }

    public static function getBoolean(string $key, bool $defaultValue = false): bool
    {
        return Cast::toBoolean(self::get($key), $defaultValue);
    }

    public static function getDuration(string $key): int
    {
        return StringUtils::toDuration(self::getString($key));
    }

    public static function getDataSize(string $key): int
    {
        return StringUtils::toDataSize(self::getString($key));
    }

    /**
     * @param string $key
     * @return int[]
     */
    public static function getIntArray(string $key): array
    {
        return Cast::toIntArray(self::get($key));
    }

    /**
     * @param string $key
     * @return string[]
     */
    public static function getStringArray(string $key): array
    {
        return Cast::toStringArray(self::get($key));
    }

    public static function getMapList(string $key): array
    {
        return Cast::toMapList(self::get($key));
    }

    private static function getData(): array
    {
        $part1 = self::getGlobalData();
        $part2 = self::mergeLocalData(self::getEnvData());

        if (!empty($part1) && empty($part2)) {
            return $part1;
        }

        if (empty($part1) && !empty($part2)) {
            return $part2;
        }

        return array_merge_recursive($part1, $part2);
    }

    private static function getGlobalData(): array
    {
        $filepath = FileUtils::getRealpath('classpath:application.yml');

        if (!is_file($filepath)) {
            return [];
        }

        try {
            $data = Yaml::parseFile($filepath);
        } catch (Throwable) {
            $data = [];
        }

        return is_array($data) ? $data : [];
    }

    private static function getEnvData(): array
    {
        $env = self::$env;
        $filepath = FileUtils::getRealpath("classpath:application-$env.yml");

        if (!is_file($filepath)) {
            return [];
        }

        try {
            $data = Yaml::parseFile($filepath);
        } catch (Throwable) {
            $data = [];
        }

        return is_array($data) ? $data : [];
    }

    private static function mergeLocalData(array $data): array
    {
        if (empty($data)) {
            return $data;
        }

        $filepath = FileUtils::getRealpath('classpath:application-local.yml');

        try {
            $map1 = Yaml::parseFile($filepath);
        } catch (Throwable) {
            $map1 = [];
        }

        if (!is_array($map1) || empty($map1)) {
            return $data;
        }

        foreach ($map1 as $key1 => $val1) {
            if (!is_array($val1)) {
                $data[$key1] = $val1;
                continue;
            }

            foreach ($val1 as $key2 => $val2) {
                if (!isset($data[$key1][$key2])) {
                    $data[$key1][$key2] = $val2;
                    continue;
                }

                if (!is_array($val2)) {
                    $data[$key1][$key2] = $val2;
                    continue;
                }

                $data[$key1][$key2] = array_merge_recursive($data[$key1][$key2], $val2);
            }
        }

        return $data;
    }

    private static function getDataFromCache(): array
    {
        if (!self::$cacheEnabled) {
            return self::getData();
        }

        $dir = self::$cacheDir;
        $cacheFile = "$dir/appconf.php";

        if (is_file($cacheFile)) {
            try {
                $data = include($cacheFile);
            } catch (Throwable) {
                $data = null;
            }

            if (is_array($data) && !empty($data)) {
                return $data;
            }

            $data = self::getData();

            if (is_array($data) && !empty($data)) {
                self::writeToCache($data);
            }

            return $data;
        }

        $data = self::getData();

        if (is_array($data) && !empty($data)) {
            self::writeToCache($data);
        }

        return $data;
    }

    private static function writeToCache(array $data): void
    {
        $dir = self::$cacheDir;
        $fp = fopen("$dir/appconf.php", 'w');

        if (!is_resource($fp)) {
            return;
        }

        $sb = [
            "<?php\n",
            'return ' . var_export($data, true) . ";\n"
        ];

        flock($fp, LOCK_EX);
        fwrite($fp, implode('', $sb));
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    private static function getValueInternal(string $mapKey, array|null $data = null): mixed
    {
        if (empty($data)) {
            $data = self::$data;
        }

        if (!is_array($data) || empty($data)) {
            return null;
        }

        $mapKey = strtolower(strtr($mapKey, ['-' => '', '_' => '']));

        foreach ($data as $key => $val) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $key = strtolower(strtr($key, ['-' => '', '_' => '']));

            if ($key === $mapKey) {
                return $val;
            }
        }

        return null;
    }
}

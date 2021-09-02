<?php

namespace mgboot\bo;

use mgboot\Cast;
use mgboot\util\ArrayUtils;
use mgboot\util\StringUtils;

final class DotAccessData
{
    private array $data;

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);

        if (!ArrayUtils::isAssocArray($data)) {
            $data = [];
        }

        return new self($data);
    }

    public static function fromArray(array $data): self
    {
        if (!ArrayUtils::isAssocArray($data)) {
            $data = [];
        }

        return new self($data);
    }

    public function get(string $key): mixed
    {
        if (!str_contains($key, '.')) {
            return $this->getValueInternal($key);
        }

        $lastKey = StringUtils::substringAfterLast($key, '.');
        $keys = explode('.', StringUtils::substringBeforeLast($key, '.'));
        $map1 = [];

        foreach ($keys as $i => $key) {
            if ($i === 0) {
                $map1 = $this->getValueInternal($key);
                continue;
            }

            if (!is_array($map1) || empty($map1)) {
                break;
            }

            $map1 = $this->getValueInternal($key, $map1);
        }

        return is_array($map1) && isset($map1[$lastKey]) ? $map1[$lastKey] : null;
    }

    public function getAssocArray(string $key): array
    {
        $map1 = $this->get($key);
        return ArrayUtils::isAssocArray($map1) ? $map1 : [];
    }

    public function getInt(string $key, int $default = PHP_INT_MIN): int
    {
        return Cast::toInt($this->get($key), $default);
    }

    public function getFloat(string $key, float $default = PHP_FLOAT_MIN): float
    {
        return Cast::toFloat($this->get($key), $default);
    }

    public function getString(string $key, string $default = ''): string
    {
        return Cast::toString($this->get($key), $default);
    }

    public function getBoolean(string $key, bool $default = false): bool
    {
        return Cast::toBoolean($this->get($key), $default);
    }

    public function getDuration(string $key): int
    {
        return Cast::toDuration($this->get($key));
    }

    public function getDataSize(string $key): int
    {
        return Cast::toDataSize($this->get($key));
    }

    /**
     * @param string $key
     * @return int[]
     */
    public function getIntArray(string $key): array
    {
        return Cast::toIntArray($this->get($key));
    }

    /**
     * @param string $key
     * @return string[]
     */
    public function getStringArray(string $key): array
    {
        return Cast::toStringArray($this->get($key));
    }

    public function getMapList(string $key): array
    {
        return Cast::toMapList($this->get($key));
    }

    private function getValueInternal(string $mapKey, array|null $data = null): mixed
    {
        if (empty($data)) {
            $data = $this->data;
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

<?php

namespace mgboot\util;

final class NumberUtils
{
    private function __construct()
    {
    }

    public static function isZero(string|int|float $arg0): bool
    {
        return bcadd($arg0, 0, 2) === '0.00';
    }

    public static function isNegative(string|int|float $arg0): bool
    {
        return str_starts_with(bcadd($arg0, 0, 2), '-');
    }

    /**
     * 取两个数的最大公约数
     *
     * @param int $m
     * @param int $n
     * @return int
     */
    public static function ojld(int $m, int $n): int
    {
        if ($m === 0 && $n === 0) {
            return 1;
        }

        if ($m === $n) {
            return $m;
        }

        if ($n === 0) {
            return $m;
        }

        while ($n !== 0) {
            $r = $m % $n;
            $m = $n;
            $n = $r;
        }

        return $m;
    }

    public static function toDecimalString(
        mixed $num,
        int $fractionDigitsNum = 2,
        bool $thousandSep = false,
        bool $stripTailsZero = true
    ): string
    {
        if ($fractionDigitsNum < 1) {
            $fractionDigitsNum = 2;
        }

        if ($fractionDigitsNum > 6) {
            $fractionDigitsNum = 6;
        }

        $num = bcadd($num, 0, 6);
        $parts = explode('.', $num);
        $p1 = $parts[0];

        if ($thousandSep) {
            $p1 = self::thousandSep($p1);
        }

        if (strlen($parts[1]) === $fractionDigitsNum) {
            $p2 = $parts[1];
        } else {
            $p2 = substr($parts[1], 0, $fractionDigitsNum);
            $n1 = (int)substr($parts[1], $fractionDigitsNum, 1);

            if ($n1 > 4) {
                $n2 = ((int)$p2) + 1;
                $p2 = StringUtils::padLeft("$n2", $fractionDigitsNum, '0');
            }
        }

        if ($stripTailsZero) {
            $p2 = rtrim($p2, '0');
        }

        return $p2 === '' ? $p1 : "$p1.$p2";
    }

    public static function thousandSep(mixed $num): string
    {
        $num = (int)$num;
        $chars = collect(StringUtils::toMbCharArray("$num"))->reverse()->toArray();
        $parts = [];

        foreach ($chars as $i => $ch) {
            $parts[] = $i % 3 === 2 ? ",$ch" : $ch;
        }

        return implode('', array_reverse($parts));
    }

    public static function toFriendlyString(mixed $num, int $n1 = 2, array $units = ['K', 'W']): string
    {
        $num = bcadd($num, 0, 2);

        if (bccomp($num, 0, 2) !== 1) {
            return '0';
        }

        if (count($units) !== 2) {
            $units = ['K', 'W'];
        }

        if (bccomp($num, 1000, 2) === -1) {
            return StringUtils::substringBefore(self::toDecimalString($num), '.');
        }

        if (bccomp($num, 10000, 2) === -1) {
            $num = bcdiv($num, 1000, 2);
            return self::toDecimalString($num, $n1) . $units[0];
        }

        $num = bcdiv($num, 10000, 2);
        return self::toDecimalString($num, $n1) . $units[1];
    }

    public static function toFriendlyDistanceString(mixed $distance, array $units = ['m', 'km']): string
    {
        $distance = bcadd($distance, 0, 2);

        if (bccomp($distance, 1000, 2) === -1) {
            return StringUtils::substringBefore($distance, '.') . $units[0];
        }

        $distance = bcdiv($distance, 1000, 2);
        return self::toDecimalString($distance, 1) . $units[1];
    }
}

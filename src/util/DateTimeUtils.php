<?php

namespace mgboot\util;

use Carbon\Carbon;
use mgboot\constant\DateTimeFormat;

final class DateTimeUtils
{
    private function __construct()
    {
    }

    public static function format(int|string $var, string $pattern = DateTimeFormat::FULL): string
    {
        if (!is_int($var) && !is_string($var)) {
            return '';
        }

        if (is_int($var)) {
            $ts = $var;
        } else {
            $ts = strtotime($var);
        }

        if (!is_int($ts) || $ts < 0) {
            return '';
        }

        return date($pattern, $ts);
    }

    public static function getWeekDayName(int|string|null $arg0 = null): string
    {
        if ($arg0 === null) {
            $ts = time();
        } else if (is_int($arg0) && $arg0 > 0) {
            $ts = $arg0;
        } else if (is_string($arg0)) {
            $ts = StringUtils::toTimestamp($arg0);
        }

        /** @var int $ts */
        if (!is_int($ts) || $ts < 1) {
            return '';
        }

        $weekDays = [
            '星期天',
            '星期一',
            '星期二',
            '星期三',
            '星期四',
            '星期五',
            '星期六'
        ];

        $idx = (int) date('w', $ts);
        return $weekDays[$idx];
    }

    public static function getDaysOfCurrentWeek(int|string|null $arg0 = null): array
    {
        if ($arg0 === null) {
            $ts = time();
        } else if (is_int($arg0) && $arg0 > 0) {
            $ts = $arg0;
        } else if (is_string($arg0)) {
            $ts = StringUtils::toTimestamp($arg0);
        }

        /** @var int $ts */
        if (!is_int($ts) || $ts < 1) {
            return [];
        }

        $n1 = ((int) date('w', $ts)) % 7;
        $d1 = Carbon::createFromTimestamp($ts);

        if ($n1 > 0) {
            $d1 = $d1->subDays($n1);
        }

        $format = DateTimeFormat::DATE_ONLY;
        $days = [$d1->format($format)];

        for ($i = 1; $i <= 6; $i++) {
            $d1 = $d1->addDay();
            $days[] = $d1->format($format);
        }

        return $days;
    }

    public static function getDaysOfPreviousWeek(int|string|null $arg0 = null): array
    {
        if ($arg0 === null) {
            $ts = time();
        } else if (is_int($arg0) && $arg0 > 0) {
            $ts = $arg0;
        } else if (is_string($arg0)) {
            $ts = StringUtils::toTimestamp($arg0);
        }

        /** @var int $ts */
        if (!is_int($ts) || $ts < 1) {
            return [];
        }

        $n1 = ((int) date('w', $ts)) % 7 + 7;
        $d1 = Carbon::createFromTimestamp($ts)->subDays($n1);
        $format = DateTimeFormat::DATE_ONLY;
        $days = [$d1->format($format)];

        for ($i = 1; $i <= 6; $i++) {
            $d1 = $d1->addDay();
            $days[] = $d1->format($format);
        }

        return $days;
    }

    public static function getDaysOfCurrentMonth(int|string|null $arg0 = null): array
    {
        if ($arg0 === null) {
            $ts = time();
        } else if (is_int($arg0) && $arg0 > 0) {
            $ts = $arg0;
        } else if (is_string($arg0)) {
            $ts = StringUtils::toTimestamp($arg0);
        }

        /** @var int $ts */
        if (!is_int($ts) || $ts < 1) {
            return [];
        }

        $year = (int) date('Y', $ts);
        $month = (int) date('n', $ts);
        $d1 = Carbon::create($year, $month);
        $n1 = $d1->daysInMonth;
        $format = DateTimeFormat::DATE_ONLY;
        $days = [$d1->format($format)];

        for ($i = 2; $i <= $n1; $i++) {
            $d1 = $d1->addDay();
            $days[] = $d1->format($format);
        }

        return $days;
    }

    public static function getDaysOfPreviousMonth(int|string|null $arg0 = null): array
    {
        if ($arg0 === null) {
            $ts = time();
        } else if (is_int($arg0) && $arg0 > 0) {
            $ts = $arg0;
        } else if (is_string($arg0)) {
            $ts = StringUtils::toTimestamp($arg0);
        }

        /** @var int $ts */
        if (!is_int($ts) || $ts < 1) {
            return [];
        }

        $year = (int) date('Y', $ts);
        $month = (int) date('n', $ts);
        $d1 = Carbon::create($year, $month)->subMonth();
        $n1 = $d1->daysInMonth;
        $format = DateTimeFormat::DATE_ONLY;
        $days = [$d1->format($format)];

        for ($i = 2; $i <= $n1; $i++) {
            $d1 = $d1->addDay();
            $days[] = $d1->format($format);
        }

        return $days;
    }

    public static function getDaysBetweenTwoDay(int|string|null $arg0, int|string $arg1): array
    {
        if (is_int($arg0) && $arg0 > 0) {
            $ts1 = $arg0;
        } else if (is_string($arg0)) {
            $ts1 = StringUtils::toTimestamp($arg0);
        }

        if (is_int($arg1) && $arg1 > 0) {
            $ts2 = $arg1;
        } else if (is_string($arg1)) {
            $ts2 = StringUtils::toTimestamp($arg1);
        }

        /** @var int $ts1 */
        /** @var int $ts2 */
        if (!is_int($ts1) || $ts1 < 1 || !is_int($ts2) || $ts2 < 1) {
            return [];
        }

        $format1 = 'Y-n-j';
        list($year1, $month1, $day1) = explode('-', date($format1, $ts1));
        list($year2, $month2, $day2) = explode('-', date($format1, $ts2));
        $d1 = Carbon::create((int) $year1, (int) $month1, (int) $day1);
        $d2 = Carbon::create((int) $year2, (int) $month2, (int) $day2);
        $format2 = DateTimeFormat::DATE_ONLY;

        if ($d1->eq($d2)) {
            return [$d1->format($format2)];
        }

        if ($d1->gt($d2)) {
            list($d1, $d2) = [$d2, $d1];
        }

        $days = [];

        while ($d1->lte($d2)) {
            $days[] = $d1->format($format2);
            $d1 = $d1->addDay();
        }

        return $days;
    }

    public static function toFriendlyString(int|string|null $arg0 = null): string
    {
        if ($arg0 === null) {
            $ts1 = time();
        } else if (is_int($arg0) && $arg0 > 0) {
            $ts1 = $arg0;
        } else if (is_string($arg0)) {
            $ts1 = StringUtils::toTimestamp($arg0);
        }

        /** @var int $ts1 */
        if (!is_int($ts1) || $ts1 < 1) {
            return '';
        }

        $ts2 = time();
        $format1 = 'Y-n-j';
        list($year1, $month1, $day1) = explode('-', date($format1, $ts1));
        $year1 = (int) $year1;
        $month1 = (int) $month1;
        $day1 = (int) $day1;
        list($year2, $month2, $day2) = explode('-', date($format1, $ts2));
        $year2 = (int) $year2;
        $month2 = (int) $month2;
        $day2 = (int) $day2;

        if ($year1 !== $year2) {
            return date('Y-m-d H:i', $ts1);
        }

        $n1 = abs($ts2 - $ts1);

        if ($n1 < 60) {
            return '1分钟前';
        }

        if ($n1 < 3600) {
            return floor($n1 / 60) . '分钟前';
        }

        if ($n1 <= (3600 * 3)) {
            $h = floor($n1 / 3600);
            $m = floor(($n1 - $h * 3600) / 60);

            if (bccomp($m, 0, 2) != 1) {
                return "{$h}小时前";
            }

            return "{$h}小时{$m}分钟前";
        }

        if ($month1 === $month2 && $day1 === $day2) {
            return date('今天 H:i', $ts1);
        }

        $d1 = Carbon::create($year1, $month1, $day1);
        $d2 = Carbon::create($year2, $month2, $day2);

        if ($d2->subDay()->eq($d1)) {
            return date('昨天 H:i', $ts1);
        }

        if ($d2->subDays(2)->eq($d1)) {
            return date('前天 H:i', $ts1);
        }

        return date('Y-m-d H:i', $ts1);
    }
}

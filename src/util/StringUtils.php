<?php

namespace mgboot\util;

use DateTime;
use DateTimeZone;
use mgboot\constant\RandomStringType;
use mgboot\constant\Regexp;
use SimpleXMLElement;
use Throwable;

final class StringUtils
{
    private function __construct()
    {
    }

    public static function equals(mixed $arg0, mixed $arg1, bool $ignoreCase = false): bool
    {
        if (!is_string($arg0) || !is_string($arg1)) {
            return false;
        }

        if ($ignoreCase) {
            $arg0 = strtolower($arg0);
            $arg1 = strtolower($arg1);
        }

        return $arg0 === $arg1;
    }

    public static function ensureLeft(string $str, string $search): string
    {
        if (self::startsWith($str, $search)) {
            return $str;
        }

        return "$search$str";
    }

    public static function ensureRight(string $str, string $search): string
    {
        if (self::endsWith($str, $search)) {
            return $str;
        }

        return "$str$search";
    }

    public static function substringBefore(string $str, string $delimiter, bool $last = false): string
    {
        $idx = $last ? mb_strrpos($str, $delimiter, 0, 'utf-8') : mb_strpos($str, $delimiter, 0, 'utf-8');

        if ($idx === false || $idx === 0) {
            return '';
        }

        if ($idx === 0) {
            return '';
        }

        return mb_substr($str, 0, $idx, 'utf-8');
    }

    public static function substringBeforeLast(string $str, string $delimiter): string
    {
        return self::substringBefore($str, $delimiter, true);
    }

    public static function substringAfter(string $str, string $delimiter, bool $last = false): string
    {
        $idx = $last ? mb_strrpos($str, $delimiter, 0, 'utf-8') : mb_strpos($str, $delimiter, 0, 'utf-8');

        if ($idx === false) {
            return '';
        }

        $n1 = $idx + mb_strlen($delimiter);
        $n2 = mb_strlen($str);

        if ($n1 >= $n2) {
            return '';
        }

        return mb_substr($str, $n1, null, 'utf-8');
    }

    public static function substringAfterLast(string $str, string $delimiter): string
    {
        return self::substringAfter($str, $delimiter, true);
    }

    /**
     * 取得指定长度的随机字符串
     *
     * @param int $len
     * @param int $type
     *              1：随机字符串由字母和数字组成
     *              2：随机字符串全部由字母组成
     *              3：随机字符串全部由数字组成
     * @return string
     */
    public static function getRandomString(int $len, int $type = RandomStringType::DEFAULT): string
    {
        $supportedTypes = [RandomStringType::DEFAULT, RandomStringType::ALPHA, RandomStringType::ALNUM];

        if (!in_array($type, $supportedTypes)) {
            $type = RandomStringType::DEFAULT;
        }

        $seeds = match ($type) {
            RandomStringType::ALPHA => 'ABCDEFGHJKLMNPQRSTUVWXYYXWVUTSRQPNMLKJHGFEDCBA',
            RandomStringType::ALNUM => '0123456789',
            default => 'ABCDEFGHJKLMNPQRSTUVWXY34567899876543YXWVUTSRQPNMLKJHGFEDCBA'
        };

        $n1 = strlen($seeds) - 1;
        $sb = [];

        for ($i = 1; $i <= $len; $i++) {
            $sb[] = $seeds[mt_rand(0, $n1)];
        }

        return implode('', $sb);
    }

    public static function toDateTime(string $str, ?DateTimeZone $tz = null): ?DateTime {
        $s1 = str_replace('-', ' ', trim($str));
        $s1 = str_replace('/', ' ', $s1);
        $s1 = str_replace(':', ' ', $s1);
        $s1 = preg_replace('/[\x20\t]+/', ' ', $s1);
        $parts = explode(' ', $s1);
        $year = $month = $day = $hours = $minutes = $seconds = -1;

        foreach ($parts as $p) {
            if (!is_numeric($p)) {
                continue;
            }

            $n1 = (int) $p;

            if ($n1 < 0) {
                continue;
            }

            if ($year < 0) {
                $year = $n1;
            } else if ($month < 0) {
                $month = $n1;
            } else if ($day < 0) {
                $day = $n1;
            } else if ($hours < 0) {
                $hours = $n1;
            } else if ($minutes < 0) {
                $minutes = $n1;
            } else if ($seconds < 0) {
                $seconds = $n1;
            }
        }

        if ($year < 1000 || $month < 1 || $month > 12) {
            return null;
        }

        $daysOfMonth = ['', 31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        if ($day > $daysOfMonth[$month]) {
            return null;
        }

        $isLeapYear = ((($year % 4) === 0) && (($year % 100) !== 0) || (($year % 400) === 0));

        if ($month === 2 && !$isLeapYear && $day > 28) {
            return null;
        }

        if ($hours < 0) {
            $hours = 0;
        } else if ($hours > 23) {
            return null;
        }

        if ($minutes < 0) {
            $minutes = 0;
        } else if ($minutes > 59) {
            return null;
        }

        if ($seconds < 0) {
            $seconds = 0;
        } else if ($seconds > 59) {
            return null;
        }

        $timeStr = sprintf(
            '%d-%02d-%02d %02d:%02d:%02d',
            $year,
            $month,
            $day,
            $hours,
            $minutes,
            $seconds
        );

        try {
            if (!($tz instanceof DateTimeZone)) {
                $tz = new DateTimeZone('Asia/Shanghai');
            }

            return DateTime::createFromFormat('Y-m-d H:i:s', $timeStr, $tz);
        } catch (Throwable) {
            return null;
        }
    }

    public static function toTimestamp(string $str): int
    {
        $d1 = self::toDateTime($str);
        return $d1 instanceof DateTime ? $d1->getTimestamp() : 0;
    }

    public static function isNationalPhoneNumber(string $str): bool
    {
        if (empty($str)) {
            return false;
        }

        $str = str_replace('（', '(', $str);
        $str = str_replace('）', ')', $str);
        $str = str_replace('－', '-', $str);
        $str = str_replace('—', '-', $str);
        $pattern1 = '/^\d{7,}$/';

        if (preg_match($pattern1, $str)) {
            return true;
        }

        $pattern2 = '/^\d{3,4}[\x20]*-[\x20]*\d{7,}$/';

        if (preg_match($pattern2, $str)) {
            return true;
        }

        $pattern3 = '/^\d{3,4}[\x20]*-[\x20]*\d{7,}[\x20]*-[\x20]*\d{1,5}$/';

        if (preg_match($pattern3, $str)) {
            return true;
        }

        $pattern4 = '/^\([\x20]*\d{3,4}[\x20]*\)[\x20]*\d{7,}$/';

        if (preg_match($pattern4, $str)) {
            return true;
        }

        $pattern5 = '/^\([\x20]*\d{3,4}[\x20]*\)[\x20]*\d{7,}[\x20]*-[\x20]*\d{1,5}$/';

        if (preg_match($pattern5, $str)) {
            return true;
        }

        return false;
    }

    public static function isNationalMobileNumber(string $str): bool
    {
        if (empty($str)) {
            return false;
        }

        if (!preg_match('/^[1-9][0-9]+$/', $str)) {
            return false;
        }

        return strlen($str) >= 11;
    }

    public static function isEmail(string $str): bool
    {
        if (empty($str)) {
            return false;
        }

        if (function_exists('filter_var')) {
            if (filter_var($str, FILTER_VALIDATE_EMAIL)) {
                return true;
            }

            return false;
        }

        if (!str_contains($str, '@')) {
            return false;
        }

        $parts = explode('@', $str);

        if (count($parts) !== 2 ||
            empty($parts[0]) ||
            empty($parts[1]) ||
            !str_contains($parts[1], '.') ||
            str_starts_with($parts[1], '.') ||
            str_ends_with($parts[1], '.')) {
            return false;
        }

        $p3 = self::substringAfterLast($parts[1], '.');

        if (preg_match('/^[A-Za-z]{2,}$/', $p3)) {
            return true;
        }

        return false;
    }

    public static function isInt(string $str): bool
    {
        if (preg_match('/^\d$/', $str)) {
            return true;
        }

        if (preg_match('/^-?[1-9]\d*$/', $str)) {
            return true;
        }

        return false;
    }

    public static function isFloat(string $str): bool
    {
        if ($str === '') {
            return false;
        }

        $parts = explode('.', $str);
        $n1 = count($parts);

        if ($n1 > 2) {
            return false;
        }

        if (!preg_match('/^-?\d+$/', $parts[0])) {
            return false;
        }

        if ($n1 < 2) {
            return true;
        }

        if (!preg_match('/^\d+$/', $parts[1])) {
            return false;
        }

        return true;
    }

    public static function isAlpha(string $str): bool
    {
        if (!preg_match('/^[A-Za-z]+$/', $str)) {
            return false;
        }

        return true;
    }

    public static function isAlnum(string $str): bool
    {
        if (!preg_match('/^[A-Za-z0-9]+$/', $str)) {
            return false;
        }

        return true;
    }

    public static function isBase64(string $str): bool
    {
        if ($str === '') {
            return false;
        }

        return base64_encode(base64_decode($str, true)) === $str;
    }

    public static function isBlank(string $str): bool
    {
        if (!preg_match('/^[[:space:]]*$/', $str)) {
            return false;
        }

        return true;
    }

    public static function isHexadecimal(string $str): bool
    {
        if (!preg_match('/^[[:xdigit:]]*$/', $str)) {
            return false;
        }

        return true;
    }

    public static function isJson(string $str): bool
    {
        if (empty($str)) {
            return false;
        }

        $flag1 = str_starts_with($str, '{') && str_ends_with($str, '}');
        $flag2 = str_starts_with($str, '[') && str_ends_with($str, ']');

        if (!$flag1 && !$flag2) {
            return false;
        }

        json_decode($str);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public static function isSerialized(string $str): bool
    {
        return $str === 'b:0;' || @unserialize($str) !== false;
    }

    public static function isDateTime(string $str): bool
    {
        return self::toDateTime($str) !== null;
    }

    public static function isDate(string $str): bool
    {
        $d1 = self::toDateTime($str);
        return $d1 instanceof DateTime && str_ends_with($d1->format('Y-m-d H:i:s'), '00:00:00');
    }

    public static function toDuration(string $str): int
    {
        if ($str === '') {
            return PHP_INT_MIN;
        }

        $n1 = 0;
        $matches = [];
        preg_match('/([1-9][0-9]*)[\x20\t]*D/i', $str, $matches);

        if (count($matches) >= 2) {
            $n1 += ((int) $matches[1]) * 24 * 60 * 60;
        }

        $matches = [];
        preg_match('/([1-9][0-9]*)[\x20\t]*H/i', $str, $matches);

        if (count($matches) >= 2) {
            $n1 += ((int) $matches[1]) * 60 * 60;
        }

        $matches = [];
        preg_match('/([1-9][0-9]*)[\x20\t]*M/i', $str, $matches);

        if (count($matches) >= 2) {
            $n1 += ((int) $matches[1]) * 60;
        }

        $matches = [];
        preg_match('/([1-9][0-9]*)[\x20\t]*S/i', $str, $matches);

        if (count($matches) >= 2) {
            $n1 += (int) $matches[1];
        }

        $str = trim($str);

        if (is_numeric($str)) {
            $n1 += (int) $str;
        }

        return $n1;
    }

    public static function toDataSize(string $str): int
    {
        if ($str === '') {
            return PHP_INT_MIN;
        }

        $n1 = 0;
        $matches = [];
        preg_match('/([1-9][0-9]*)[\x20\t]*G/i', $str, $matches);

        if (count($matches) >= 2) {
            $n1 += ((int) $matches[1]) * 1024 * 1024 * 1024;
        }

        $matches = [];
        preg_match('/([1-9][0-9]*)[\x20\t]*M/i', $str, $matches);

        if (count($matches) >= 2) {
            $n1 += ((int) $matches[1]) * 1024 * 1024;
        }

        $matches = [];
        preg_match('/([1-9][0-9]*)[\x20\t]*K/i', $str, $matches);

        if (count($matches) >= 2) {
            $n1 += ((int) $matches[1]) * 1024;
        }

        $matches = [];
        preg_match('/([1-9][0-9]*)[\x20\t]*B/i', $str, $matches);

        if (count($matches) >= 2) {
            $n1 += (int) $matches[1];
        }

        $str = trim($str);

        if (is_numeric($str)) {
            $n1 += (int) $str;
        }

        return $n1;
    }

    public static function xml2assocArray(string $xml): array
    {
        if (empty($xml)) {
            return [];
        }

        if (version_compare(PHP_VERSION, '8.0.0') === -1) {
            /** @noinspection PhpDeprecationInspection */
            libxml_disable_entity_loader(true);
        }

        $element = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        return $element instanceof SimpleXMLElement ? get_object_vars($element) : [];
    }

    public static function isPasswordTooSimple(string $str): bool
    {
        //ascii A-Z
        $a1 = range(ord('A'), ord('Z'));

        //ascii a-z
        $a2 = range(ord('a'), ord('z'));

        //ascii 0-9
        $a3 = range(ord('0'), ord('9'));

        $n1 = 0;
        $n2 = 0;
        $n3 = 0;
        $n4 = 0;

        foreach (self::toMbCharArray($str) as $ch) {
            if (($n1 + $n2 + $n3 + $n4) >= 3) {
                return false;
            }

            $ascii = ord($ch);

            if (in_array($ascii, $a1)) {
                $n1 = 1;
                continue;
            }

            if (in_array($ascii, $a2)) {
                $n2 = 1;
                continue;
            }

            if (in_array($ascii, $a3)) {
                $n3 = 1;
                continue;
            }

            $n4 = 1;
        }

        return ($n1 + $n2 + $n3 + $n4) < 3;
    }

    public static function toMbCharArray(string $str): array
    {
        if ($str === '') {
            return [];
        }

        $encoding = 'utf-8';
        $n1 = mb_strlen($str, $encoding) - 1;
        $chars = [];

        for ($i = 0; $i <= $n1; $i++) {
            if ($i === $n1) {
                $chars[] = mb_substr($str, $i, null, $encoding);
                break;
            }

            $chars[] = mb_substr($str, $i, 1, $encoding);
        }

        return $chars;
    }

    public static function maskEmail(string $str): string
    {
        if (!self::isEmail($str)) {
            return $str;
        }

        $p1 = self::substringBefore($str, '@');
        $p2 = self::substringAfter($str, '@');
        $n1 = strlen($p1);

        if ($n1 <= 4) {
            $p1 = self::maskString($p1, 1, 1);
        } else if ($n1 <= 6) {
            $p1 = self::maskString($p1, 2, 2);
        } else {
            $p1 = self::maskString($p1, 3, 3);
        }

        return "$p1@$p2";
    }

    public static function maskString(string $str, int $prefixLen, int $suffixLen = PHP_INT_MIN): string
    {
        if ($prefixLen < 1) {
            return $str;
        }

        $suffixLen = $suffixLen > 0 ? $suffixLen : 0;
        $len = strlen($str);

        if ($len <= $prefixLen + $suffixLen) {
            return $str;
        }

        $p1 = substr($str, 0, $prefixLen);
        $p2 = $suffixLen > 0 ? substr($str, -$suffixLen) : '';
        $p3 = str_repeat('*', $len - $prefixLen - $suffixLen);
        return "$p1$p3$p2";
    }

    public static function mbMaskString(string $str, int $prefixLen, int $suffixLen = PHP_INT_MIN): string
    {
        if ($prefixLen < 1) {
            return $str;
        }

        $suffixLen = $suffixLen > 0 ? $suffixLen : 0;
        $len = mb_strlen($str, 'utf-8');

        if ($len <= $prefixLen + $suffixLen) {
            return $str;
        }

        $p1 = mb_substr($str, 0, $prefixLen, 'utf-8');
        $p2 = $suffixLen > 0 ? mb_substr($str, -$suffixLen, null, 'utf-8') : '';
        $p3 = str_repeat('*', $len - $prefixLen - $suffixLen);
        return "$p1$p3$p2";
    }

    public static function mbSubString(string $str, int $len, string $suffix = '...'): string
    {
        $encoding = 'utf-8';
        $n = mb_strlen($suffix, $encoding);

        if (mb_strlen($str, $encoding) <= $len) {
            return $str;
        }

        if ($n < 1) {
            return mb_substr($str, 0, $len, $encoding);
        }

        return mb_substr($str, 0, $len - $n, $encoding) . $suffix;
    }

    public static function removeSqlSpecialChars(string|array $var): string|array
    {
        if (is_array($var)) {
            foreach ($var as $key => $item) {
                $var[$key] = self::removeSqlSpecialChars($item);
            }

            return $var;
        }

        if (!is_string($var)) {
            return $var;
        }

        $var = str_replace("\r", '', $var);
        $var = str_replace("\n", '', $var);
        $var = str_replace("\\", '', $var);
        $var = str_replace("'", '', $var);
        return str_replace('"', '', $var);
    }

    public static function ucwords(string $str, string $joinBy = '-', string... $delimiters): string
    {
        if (empty($delimiters) || !ArrayUtils::isStringArray($delimiters)) {
            $delimiters = [' ', '-', '_'];
        } else {
            $delimiters = array_values(array_filter($delimiters, fn($it) => strlen($it) === 1));
        }

        if (empty($delimiters)) {
            return $str;
        }

        foreach ($delimiters as $ch) {
            if ($ch === ' ') {
                continue;
            }

            $str = str_replace($ch, ' ', $str);
        }

        $str = preg_replace(Regexp::SPACE_SEP, ' ', $str);
        $parts = array_map(fn($it) => ucfirst($it), explode(' ', $str));
        return implode($joinBy, $parts);
    }

    public static function lcwords(string $str, string $joinBy = ' ', string... $delimiters): string
    {
        if (empty($delimiters) || !ArrayUtils::isStringArray($delimiters)) {
            $delimiters = [' ', '-', '_'];
        } else {
            $delimiters = array_values(array_filter($delimiters, fn($it) => strlen($it) === 1));
        }

        if (empty($delimiters)) {
            return $str;
        }

        foreach ($delimiters as $ch) {
            if ($ch === ' ') {
                continue;
            }

            $str = str_replace($ch, ' ', $str);
        }

        $str = preg_replace(Regexp::SPACE_SEP, ' ', $str);
        $parts = array_map(fn($it) => lcfirst($it), explode(' ', $str));
        return implode($joinBy, $parts);
    }

    public static function startsWith(string $str, string $search, $caseSensitive = true): bool
    {
        $encoding = 'utf-8';
        $n1 = mb_strlen($str, $encoding);
        $n2 = mb_strlen($search, $encoding);

        if ($n1 < $n2) {
            return false;
        }

        return $caseSensitive ? mb_strpos($str, $search, 0, $encoding) === 0 : mb_stripos($str, $search, 0, $encoding) === 0;
    }

    public static function startsWithAny(string $str, array $searchs, bool $caseSensitive = true): bool
    {
        foreach ($searchs as $search) {
            if (!is_string($search) || $search === '') {
                continue;
            }

            if (self::startsWith($str, $search, $caseSensitive)) {
                return true;
            }
        }

        return false;
    }

    public static function endsWith(string $str, string $search, bool $caseSensitive = true): bool
    {
        $encoding = 'utf-8';
        $n1 = mb_strlen($str, $encoding);
        $n2 = mb_strlen($search, $encoding);

        if ($n1 < $n2) {
            return false;
        }

        $part = mb_substr($str, -$n2, null, $encoding);

        if ($caseSensitive) {
            return $part === $search;
        }


        return mb_strtolower($part, $encoding) === mb_strtolower($search, $encoding);
    }

    public static function endsWithAny(string $str, array $searchs, bool $caseSensitive = true): bool
    {
        foreach ($searchs as $search) {
            if (!is_string($search) || $search === '') {
                continue;
            }

            if (self::startsWith($str, $search, $caseSensitive)) {
                return true;
            }
        }

        return false;
    }

    public static function padLeft(string $str, int $len, string $padStr = ' '): string
    {
        $n1 = mb_strlen($str, 'utf-8');

        if ($n1 >= $len) {
            return $str;
        }

        return str_repeat($padStr, $len - $n1) . $str;
    }

    public static function padRight(string $str, int $len, string $padStr = ' '): string
    {
        $n1 = mb_strlen($str, 'utf-8');

        if ($n1 >= $len) {
            return $str;
        }

        return $str . str_repeat($padStr, $len - $n1);
    }

    public static function shuffle(string $str): string
    {
        $chars = self::toMbCharArray($str);

        if (empty($chars)) {
            return '';
        }

        shuffle($chars);
        return implode('', $chars);
    }

    public static function slice(string $str, int $start, int $end = PHP_INT_MIN): string
    {
        if ($str === '') {
            return $str;
        }

        if ($start < 0) {
            $start = 0;
        }

        $chars = self::toMbCharArray($str);
        $posRange = range(0, count($chars) - 1);

        if ($end < $start) {
            $nums = array_values(array_filter($posRange, fn($it) => $it >= $start));
        } else {
            $nums = array_values(array_filter($posRange, fn($it) => $it >= $start && $it < $end));
        }

        $parts = array_map(fn($it) => $chars[$it], $nums);
        return implode('', $parts);
    }

    public static function getAgeByBirthday(string $birthday): int
    {
        $d1 = self::toDateTime($birthday);

        if (!($d1 instanceof DateTime)) {
            return -1;
        }

        $year1 = (int) date('Y', $d1->getTimestamp());
        $month1 = (int) date('n', $d1->getTimestamp());
        $day1 = (int) date('j', $d1->getTimestamp());
        $year2 = (int) date('Y') - 1;
        $month2 = (int) date('n');
        $day2 = (int) date('j');
        $age = $year2 - $year1;

        if ($age < 0) {
            return -1;
        }

        if ($month2 > $month1) {
            $age += 1;
        } else if ($month2 === $month1 && $day2 >= $day1) {
            $age += 1;
        }

        return $age > 0 ? $age : -1;
    }

    public static function md5WithSalt(string $str, string $salt): string
    {
        return md5(md5($str) . $salt);
    }

    public static function getBirthdayByIdcardNo(string $str): string
    {
        $y1 = substr($str, -12, 4);
        $m1 = substr($str, -8, 2);
        $d1 = substr($str, -6, 2);
        $birthday = "$y1-$m1-$d1";
        return self::isDate($birthday) ? $birthday : '';
    }

    public static function removeEmoji(string $str): string
    {
        if ($str === '') {
            return $str;
        }

        $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
        $str = preg_replace($regexEmoticons, '', $str);

        $regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
        $str = preg_replace($regexSymbols, '', $str);

        $regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
        $str = preg_replace($regexTransport, '', $str);

        $regexMisc = '/[\x{2600}-\x{26FF}]/u';
        $str = preg_replace($regexMisc, '', $str);

        $regexDingbats = '/[\x{2700}-\x{27BF}]/u';
        return preg_replace($regexDingbats, '', $str);
    }

    public static function camelCase(string $str): string
    {
        if (empty($str)) {
            return $str;
        }

        $str = trim($str);
        $str = strtr($str, ['-' => ' ', '_' => ' ']);
        $str = preg_replace(Regexp::SPACE_SEP, ' ', $str);
        $str = str_replace(' ', '', ucwords($str));
        return lcfirst($str);
    }
}

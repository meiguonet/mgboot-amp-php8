<?php

namespace mgboot\util;

use RuntimeException;
use Throwable;

final class ExceptionUtils
{
    private function __construct()
    {
    }


    public static function asRuntimeException(Throwable $ex): RuntimeException
    {
        if ($ex instanceof RuntimeException) {
            return $ex;
        }

        $msg = $ex->getMessage();
        return new RuntimeException(empty($msg) ? '' : $msg, 0, $ex);
    }

    public static function getStackTraceLines(Throwable $ex): array
    {
        $lines = [];
        $clazz = get_class($ex);
        $msg = $ex->getMessage();
        $sb = [$clazz];

        if (is_string($msg) && $msg !== '') {
            $sb[] = ": $msg";
        }

        $lines[] = implode('', $sb);
        $traces = $ex->getTrace();
        $filePath = $ex->getFile();
        $lineNumber = $ex->getLine();
        $sb = ["at $filePath 第 $lineNumber 行"];

        if (!empty($traces[0]['class'])) {
            $sb[] = ": {$traces[0]['class']}";

            if (!empty($traces[0]['function'])) {
                $sb[] = "{$traces[0]['type']}{$traces[0]['function']}(...)";
            }
        } else if (!empty($traces[0]['function'])) {
            $sb[] = ": {$traces[0]['function']}(...)";
        }

        $lines[] = implode('', $sb);

        foreach ($traces as $i => $trace) {
            if ($i === 0) {
                continue;
            }

            $sb = [];

            if (!empty($trace['file'])) {
                $sb[] = "at {$trace['file']}";
            }

            if (!empty($trace['line'])) {
                $sb[] = " 第 {$trace['line']} 行";
            }

            if (!empty($trace['class']) && !empty($sb)) {
                $sb[] = ": {$trace['class']}";

                if (!empty($trace['function'])) {
                    $sb[] = "{$trace['type']}{$trace['function']}(...)";
                }
            } else if (!empty($trace['function']) && !empty($sb)) {
                $sb[] = ": {$trace['function']}(...)";
            }

            if (!empty($sb)) {
                $lines[] = implode('', $sb);
            }
        }

        return $lines;
    }

    public static function getStackTrace(Throwable $ex, string $seperator = "\n"): string
    {
        return implode($seperator, self::getStackTraceLines($ex));
    }
}

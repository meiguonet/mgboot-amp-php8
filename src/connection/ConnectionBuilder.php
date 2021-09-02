<?php

namespace mgboot\connection;

use mgboot\MgBoot;
use PDO;
use Redis;
use Throwable;

final class ConnectionBuilder
{

    private function __construct()
    {
    }

    public static function buildPdoConnection(): ?PDO
    {
        $cfg = MgBoot::getDbConfig();

        if (!$cfg->isEnabled()) {
            return null;
        }

        $cliSettings = $cfg->getCliSettings();

        if (!is_array($cliSettings)) {
            $cliSettings = [];
        }

        $host = $cfg->getHost();

        if (is_string($cliSettings['host']) && $cliSettings['host'] !== '') {
            $host = $cliSettings['host'];
        }

        if ($host === '') {
            $host = '127.0.0.1';
        }

        $port = $cfg->getPort();

        if (is_int($cliSettings['port']) && $cliSettings['port'] > 0) {
            $port = $cliSettings['port'];
        }

        if ($port < 1) {
            $port = 3306;
        }

        $username = $cfg->getUsername();

        if (is_string($cliSettings['username']) && $cliSettings['username'] !== '') {
            $username = $cliSettings['username'];
        }

        if ($username === '') {
            $username = 'root';
        }

        $password = $cfg->getPassword();

        if (is_string($cliSettings['password']) && $cliSettings['password'] !== '') {
            $password = $cliSettings['password'];
        }

        $dbname = $cfg->getDbname();

        if (is_string($cliSettings['database']) && $cliSettings['database'] !== '') {
            $dbname = $cliSettings['database'];
        }

        if ($dbname === '') {
            $dbname = 'test';
        }

        $charset = $cfg->getCharset();

        if ($charset === '') {
            $charset = 'utf8mb4';
        }

        $dsn = "mysql:dbname=$dbname;host=$host;port=$port;charset=$charset";

        $opts = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ];

        try {
            return new PDO($dsn, $username, $password, $opts);
        } catch (Throwable) {
            return null;
        }
    }

    public static function buildRedisConnection(): ?Redis
    {
        $cfg = MgBoot::getRedisConfig();

        if (!$cfg->isEnabled()) {
            return null;
        }

        $cliSettings = $cfg->getCliSettings();

        if (!is_array($cliSettings)) {
            $cliSettings = [];
        }

        $host = $cfg->getHost();

        if (is_string($cliSettings['host']) && $cliSettings['host'] !== '') {
            $host = $cliSettings['host'];
        }

        if ($host === '') {
            $host = '127.0.0.1';
        }

        $port = $cfg->getPort();

        if (is_int($cliSettings['port']) && $cliSettings['port'] > 0) {
            $port = $cliSettings['port'];
        }

        if ($port < 1) {
            $port = 6379;
        }

        $password = $cfg->getPassword();

        if (is_string($cliSettings['password']) && $cliSettings['password'] !== '') {
            $password = $cliSettings['password'];
        }

        $database = $cfg->getDatabase();

        if (is_int($cliSettings['database']) && $cliSettings['database'] >= 0) {
            $database = $cliSettings['database'];
        }

        $readTimeout = $cfg->getReadTimeout();

        if ($readTimeout < 1) {
            $readTimeout = 5;
        }

        try {
            $redis = new Redis();

            if (!$redis->connect($host, $port, 1.0, null, 0, $readTimeout)) {
                return null;
            }

            if ($password !== '' && !$redis->auth($password)) {
                $redis->close();
                return null;
            }

            if ($database > 0 && !$redis->select($database)) {
                $redis->close();
                return null;
            }

            return $redis;
        } catch (Throwable) {
            return null;
        }
    }
}

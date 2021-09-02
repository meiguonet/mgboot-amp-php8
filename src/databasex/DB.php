<?php

namespace mgboot\databasex;

use Closure;
use Generator;
use Illuminate\Support\Collection;
use mgboot\AppConf;
use mgboot\Cast;
use mgboot\connection\ConnectionBuilder;
use mgboot\constant\Regexp;
use mgboot\exception\DbException;
use mgboot\MgBoot;
use mgboot\util\ExceptionUtils;
use mgboot\util\FileUtils;
use mgboot\util\JsonUtils;
use mgboot\util\StringUtils;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Throwable;
use function Amp\call;

final class DB
{
    private static ?LoggerInterface $logger;
    private static bool $debugLogEnabled = false;
    private static string $cacheDir = 'classpath:cache';
    private static array $tableSchemas = [];

    private function __construct()
    {
    }

    private function __clone(): void
    {
    }

    public static function withLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    public static function enableDebugLog(): void
    {
        self::$debugLogEnabled = true;
    }

    public static function withCacheDir(string $dir): void
    {
        if ($dir !== '' && is_dir($dir) && is_writable($dir)) {
            self::$cacheDir = $dir;
        }
    }

    public static function buildTableSchemas(): void
    {
        $inDevMode = AppConf::getEnv() === 'dev';

        if ($inDevMode) {
            return;
        }

        self::$tableSchemas = self::buildTableSchemasFromCacheFile();
    }

    public static function getTableSchema(string $tableName): array
    {
        $tableName = str_replace('`', '', $tableName);

        if (str_contains($tableName, '.')) {
            $tableName = StringUtils::substringAfterLast($tableName, '.');
        }

        if (AppConf::getEnv() === 'dev') {
            $schemas = self::buildTableSchemasInternal();
        } else {
            $schemas = self::$tableSchemas;

            if (empty($schemas)) {
                self::buildTableSchemas();
                $schemas = self::$tableSchemas;
            }
        }

        return is_array($schemas) && isset($schemas[$tableName]) ? $schemas[$tableName] : [];
    }

    public static function table(string $tableName): QueryBuilder
    {
        return QueryBuilder::create($tableName);
    }

    public static function raw(string $expr): Expression
    {
        return Expression::create($expr);
    }

    public static function selectBySql(string $sql, array $params = [], ?PDO $pdo = null): Collection
    {
        self::logSql($sql, $params);

        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare($sql);

                if (!($stmt instanceof PDOStatement)) {
                    return collect([]);
                }

                self::pdoBindParams($stmt, $params);
                $stmt->execute();
                return collect($stmt->fetchAll());
            } catch (Throwable $ex) {
                $ex = self::wrapAsDbException($ex);
                self::writeErrorLog($ex);
                throw $ex;
            }
        }

        list($result, $errorTips) = self::sendToGobackend('@@select', $sql, $params);

        if (!empty($errorTips)) {
            $ex = new DbException(null, $errorTips);
            self::writeErrorLog($ex);
            throw $ex;
        }

        return collect(JsonUtils::arrayFrom($result));
    }

    public static function firstBySql(string $sql, array $params = [], ?PDO $pdo = null): ?array
    {
        self::logSql($sql, $params);

        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare($sql);

                if (!($stmt instanceof PDOStatement)) {
                    return null;
                }

                self::pdoBindParams($stmt, $params);
                $stmt->execute();
                $data = $stmt->fetch();
                return is_array($data) ? $data : null;
            } catch (Throwable $ex) {
                $ex = self::wrapAsDbException($ex);
                self::writeErrorLog($ex);
                throw $ex;
            }
        }

        list($result, $errorTips) = self::sendToGobackend('@@first', $sql, $params);

        if (!empty($errorTips)) {
            $ex = new DbException(null, $errorTips);
            self::writeErrorLog($ex);
            throw $ex;
        }

        $map1 = JsonUtils::mapFrom($result);
        return is_array($map1) ? $map1 : null;
    }

    public static function countBySql(string $sql, array $params = [], ?PDO $pdo = null): int
    {
        self::logSql($sql, $params);

        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare($sql);

                if (!($stmt instanceof PDOStatement)) {
                    return 0;
                }

                self::pdoBindParams($stmt, $params);
                $stmt->execute();
                return (int) $stmt->fetchColumn();
            } catch (Throwable $ex) {
                $ex = self::wrapAsDbException($ex);
                self::writeErrorLog($ex);
                throw $ex;
            }
        }

        list($result, $errorTips) = self::sendToGobackend('@@count', $sql, $params);

        if (!empty($errorTips)) {
            $ex = new DbException(null, $errorTips);
            self::writeErrorLog($ex);
            throw $ex;
        }

        return Cast::toInt($result, 0);
    }

    public static function insertBySql(string $sql, array $params = [], ?PDO $pdo = null): int
    {
        self::logSql($sql, $params);

        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare($sql);

                if (!($stmt instanceof PDOStatement)) {
                    return 0;
                }

                self::pdoBindParams($stmt, $params);

                if (!$stmt->execute()) {
                    return 0;
                }

                return (int) $pdo->lastInsertId();
            } catch (Throwable $ex) {
                $ex = self::wrapAsDbException($ex);
                self::writeErrorLog($ex);
                throw $ex;
            }
        }

        list($result, $errorTips) = self::sendToGobackend('@@insert', $sql, $params);

        if (!empty($errorTips)) {
            $ex = new DbException(null, $errorTips);
            self::writeErrorLog($ex);
            throw $ex;
        }

        return Cast::toInt($result, 0);
    }

    public static function updateBySql(string $sql, array $params = [], ?PDO $pdo = null): int
    {
        self::logSql($sql, $params);

        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare($sql);

                if (!($stmt instanceof PDOStatement)) {
                    return 0;
                }

                self::pdoBindParams($stmt, $params);

                if (!$stmt->execute()) {
                    return 0;
                }

                return $stmt->rowCount();
            } catch (Throwable $ex) {
                $ex = self::wrapAsDbException($ex);
                self::writeErrorLog($ex);
                throw $ex;
            }
        }

        list($result, $errorTips) = self::sendToGobackend('@@update', $sql, $params);

        if (!empty($errorTips)) {
            $ex = new DbException(null, $errorTips);
            self::writeErrorLog($ex);
            throw $ex;
        }

        return Cast::toInt($result, -1);
    }

    public static function sumBySql(string $sql, array $params = [], ?PDO $pdo = null): int|float|string
    {
        self::logSql($sql, $params);

        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare($sql);

                if (!($stmt instanceof PDOStatement)) {
                    return 0;
                }

                self::pdoBindParams($stmt, $params);

                if (!$stmt->execute()) {
                    return 0;
                }

                $value = $stmt->fetchColumn();

                if (is_int($value) || is_float($value)) {
                    return $value;
                }

                if (!is_string($value) || $value === '') {
                    return 0;
                }

                if (StringUtils::isInt($value)) {
                    return Cast::toInt($value);
                }

                if (StringUtils::isFloat($value)) {
                    return bcadd($value, 0, 2);
                }

                return 0;
            } catch (Throwable $ex) {
                $ex = self::wrapAsDbException($ex);
                self::writeErrorLog($ex);
                throw $ex;
            }
        }

        list($result, $errorTips) = self::sendToGobackend('@@sum', $sql, $params);

        if (!empty($errorTips)) {
            $ex = new DbException(null, $errorTips);
            self::writeErrorLog($ex);
            throw $ex;
        }

        $map1 = JsonUtils::mapFrom($result);

        if (!is_array($map1)) {
            return '0.00';
        }

        $num = $map1['sum'];
        return is_int($num) || is_float($num) ? $num : bcadd($num, 0, 2);
    }

    public static function deleteBySql(string $sql, array $params = [], ?PDO $pdo = null): int
    {
        return self::updateBySql($sql, $params, $pdo);
    }

    public static function executeSql(string $sql, array $params = [], ?PDO $pdo = null): void
    {
        self::logSql($sql, $params);

        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare($sql);

                if (!($stmt instanceof PDOStatement)) {
                    return;
                }

                self::pdoBindParams($stmt, $params);
                $stmt->execute();
            } catch (Throwable $ex) {
                $ex = self::wrapAsDbException($ex);
                self::writeErrorLog($ex);
                throw $ex;
            }

            return;
        }

        list(, $errorTips) = self::sendToGobackend('@@execute', $sql, $params);

        if (!empty($errorTips)) {
            $ex = new DbException(null, $errorTips);
            self::writeErrorLog($ex);
            throw $ex;
        }
    }

    public static function transations(Closure $callback): void
    {
        if (MgBoot::inAmpMode()) {
            foreach (self::transationsAsync($callback) as $ex) {
                if ($ex instanceof DbException) {
                    throw $ex;
                }

                break;
            }

            return;
        }

        $pdo = ConnectionBuilder::buildPdoConnection();

        if ($pdo === null) {
            throw new DbException(null, 'fail to get database connection');
        }

        try {
            $pdo->beginTransaction();
            $callback->call($pdo);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $ex = self::wrapAsDbException($ex);
            self::writeErrorLog($ex);
            throw $ex;
        } finally {
            unset($pdo);
        }
    }

    private static function transationsAsync(Closure $callback): Generator
    {
        yield call(function () use ($callback) {
            $pdo = ConnectionBuilder::buildPdoConnection();

            if ($pdo === null) {
                throw new DbException(null, 'fail to get database connection');
            }

            try {
                $pdo->beginTransaction();
                $callback->call($pdo);
                $pdo->commit();
                return true;
            } catch (Throwable $ex) {
                $pdo->rollBack();
                $ex = self::wrapAsDbException($ex);
                self::writeErrorLog($ex);
                throw $ex;
            } finally {
                unset($pdo);
            }
        });
    }

    private static function pdoBindParams(PDOStatement $stmt, array $params): void
    {
        if (empty($params)) {
            return;
        }

        foreach ($params as $i => $value) {
            if ($value === null) {
                $stmt->bindValue($i + 1, null, PDO::PARAM_NULL);
                continue;
            }

            if (is_int($value)) {
                $stmt->bindValue($i + 1, $value, PDO::PARAM_INT);
                continue;
            }

            if (is_float($value)) {
                $stmt->bindValue($i + 1, "$value");
                continue;
            }

            if (is_string($value)) {
                $stmt->bindValue($i + 1, $value);
                continue;
            }

            if (is_bool($value)) {
                $stmt->bindValue($i + 1, $value, PDO::PARAM_BOOL);
                continue;
            }

            if (is_array($value)) {
                throw new DbException(null, 'fail to bind param, param type: array');
            }

            if (is_resource($value)) {
                throw new DbException(null, 'fail to bind param, param type: resource');
            }

            if (is_object($value)) {
                throw new DbException(null, 'fail to bind param, param type: ' . $value::class);
            }
        }
    }

    private static function buildTableSchemasInternal(): array
    {
        $pdo = ConnectionBuilder::buildPdoConnection();

        if ($pdo === null) {
            return [];
        }

        $tables = [];

        try {
            $stmt = $pdo->prepare('SHOW TABLES');
            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!is_array($records) || empty($records)) {
                unset($pdo);
                return [];
            }

            foreach ($records as $record) {
                foreach ($record as $key => $value) {
                    if (str_contains($key, 'Tables_in')) {
                        $tables[] = trim($value);
                        break;
                    }
                }
            }
        } catch (Throwable) {
            unset($pdo);
            return [];
        }

        if (empty($tables)) {
            unset($pdo);
            return [];
        }

        $schemas = [];

        foreach ($tables as $tableName) {
            try {
                $stmt = $pdo->prepare("DESC $tableName");
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!is_array($items) || empty($items)) {
                    continue;
                }

                $schema = collect($items)->map(function ($item) {
                    $fieldName = $item['Field'];
                    $nullable = stripos($item['Null'], 'YES') !== false;
                    $isPrimaryKey = $item['Key'] === 'PRI';
                    $defaultValue = $item['Default'];
                    $autoIncrement = $item['Extra'] === 'auto_increment';
                    $parts = preg_split(Regexp::SPACE_SEP, $item['Type']);

                    if (str_contains($parts[0], '(')) {
                        $fieldType = StringUtils::substringBefore($parts[0], '(');
                        $fieldSize = str_replace($fieldType, '', $parts[0]);
                    } else {
                        $fieldType = $parts[0];
                        $fieldSize = '';
                    }

                    if (!str_starts_with($fieldSize, '(') || !str_ends_with($fieldSize, ')')) {
                        $fieldSize = '';
                    } else {
                        $fieldSize = rtrim(ltrim($fieldSize, '('), ')');
                    }

                    if (is_numeric($fieldSize)) {
                        $fieldSize = (int) $fieldSize;
                    }

                    $unsigned = stripos($item['Type'], 'unsigned') !== false;

                    return compact(
                        'fieldName',
                        'fieldType',
                        'fieldSize',
                        'unsigned',
                        'nullable',
                        'defaultValue',
                        'autoIncrement',
                        'isPrimaryKey'
                    );
                })->toArray();
            } catch (Throwable) {
                $schema = null;
            }

            if (!is_array($schema) || empty($schema)) {
                continue;
            }

            $schemas[$tableName] = $schema;
        }

        unset($pdo);
        return $schemas;
    }

    private static function buildTableSchemasFromCacheFile(): array
    {
        $dir = FileUtils::getRealpath(self::$cacheDir);

        if (!is_dir($dir)) {
            return self::buildTableSchemasInternal();
        }

        $cacheFile = "$dir/table_schemas.php";
        $schemas = [];

        if (is_file($cacheFile)) {
            try {
                $schemas = include($cacheFile);
            } catch (Throwable) {
                $schemas = [];
            }
        }

        if (is_array($schemas) && !empty($schemas)) {
            return $schemas;
        }

        $schemas = self::buildTableSchemasInternal();
        self::writeTableSchemasToCacheFile($schemas);
        return $schemas;
    }

    private static function writeTableSchemasToCacheFile(array $schemas): void
    {
        if (empty($schemas)) {
            return;
        }

        $dir = FileUtils::getRealpath(self::$cacheDir);

        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }

        $cacheFile = "$dir/table_schemas.php";
        $fp = fopen($cacheFile, 'w');

        if (!is_resource($fp)) {
            return;
        }

        $sb = [
            "<?php\n",
            'return ' . var_export($schemas, true) . ";\n"
        ];

        flock($fp, LOCK_EX);
        fwrite($fp, implode('', $sb));
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    private static function sendToGobackend(string $cmd, string $query, array $params): array
    {
        $cfg = MgBoot::getGobackendSettings();

        if (!$cfg->isEnabled()) {
            return ['', 'fail to load gobackend settings'];
        }

        $host = $cfg->getHost();
        $port = $cfg->getPort();

        $timeout = match ($cmd) {
            '@@select' => 120.0,
            '@@first', '@@count', '@@sum' => 10.0,
            default => 5.0
        };

        $maxPkgLength = match ($cmd) {
            '@@select' => 8 * 1024 * 1024,
            '@@first' => 16 * 1024,
            default => 256
        };

        $msg = "@@db:$cmd:$query";

        if (!empty($params)) {
            $msg .= '@^sep^@' . JsonUtils::toJson($params);
        }

        if (MgBoot::inAmpMode()) {
            foreach (self::sendToGobackendAsync($host, $port, $timeout, $maxPkgLength, $msg) as $it) {
                if ($it instanceof DbException) {
                    return ['', $it->getMessage()];
                }

                return $it;
            }
        }

        $fp = fsockopen($host, $port);

        if (!is_resource($fp)) {
            $errorTips = 'fail to connect to gobackend';
            return ['', $errorTips];
        }

        try {
            stream_set_timeout($fp, $timeout);
            fwrite($fp, $msg);
            $sb = [];

            while (!feof($fp)) {
                $buf = fread($fp, $maxPkgLength);

                if (!is_string($buf)) {
                    continue;
                }

                $sb[] = $buf;
            }

            $result = trim(str_replace('@^@end', '', implode('', $sb)));

            if (str_starts_with($result, '@@error:')) {
                return ['', str_replace('@@error:', '', $result)];
            }

            return [$result, ''];
        } catch (Throwable $ex) {
            return ['', $ex->getMessage()];
        } finally {
            fclose($fp);
        }
    }

    private static function sendToGobackendAsync(string $host, int $port, int $timeout, int $maxPkgLength, string $msg): Generator
    {
        yield call(function () use ($host, $port, $timeout, $maxPkgLength, $msg, &$payloads) {
            try {
                $fp = fsockopen($host, $port);
            } catch (Throwable) {
                $fp = null;
            }

            if (!is_resource($fp)) {
                $errorTips = 'fail to connect to gobackend';
                return ['', $errorTips];
            }

            try {
                stream_set_timeout($fp, $timeout);
                fwrite($fp, $msg);
                $sb = [];

                while (!feof($fp)) {
                    $buf = fread($fp, $maxPkgLength);

                    if (!is_string($buf)) {
                        continue;
                    }

                    $sb[] = $buf;
                }

                $result = trim(str_replace('@^@end', '', implode('', $sb)));

                if (str_starts_with($result, '@@error:')) {
                    return ['', str_replace('@@error:', '', $result)];
                }

                return [$result, ''];
            } catch (Throwable $ex) {
                return ['', $ex->getMessage()];
            } finally {
                fclose($fp);
            }
        });
    }

    private static function wrapAsDbException(Throwable $ex): DbException
    {
        if ($ex instanceof DbException) {
            return $ex;
        }

        return new DbException(null, $ex->getMessage());
    }

    private static function logSql(string $sql, ?array $params = null): void
    {
        $logger = self::$logger;

        if (!($logger instanceof LoggerInterface) || !self::$debugLogEnabled) {
            return;
        }

        $logger->info($sql);

        if (is_array($params) && !empty($params)) {
            $logger->debug('params: ' . JsonUtils::toJson($params));
        }
    }

    private static function writeErrorLog(string|Throwable $msg): void
    {
        $logger = self::$logger;

        if (!($logger instanceof LoggerInterface)) {
            return;
        }

        if ($msg instanceof Throwable) {
            $msg = ExceptionUtils::getStackTrace($msg);
        }

        $logger->error($msg);
    }
}

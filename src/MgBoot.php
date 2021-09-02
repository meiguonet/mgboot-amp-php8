<?php

namespace mgboot;

use FastRoute\Dispatcher;
use mgboot\bo\DbConfig;
use mgboot\bo\GobackendSettings;
use mgboot\bo\RedisConfig;
use mgboot\exception\AccessTokenExpiredException;
use mgboot\exception\AccessTokenInvalidException;
use mgboot\exception\DataValidateException;
use mgboot\exception\ExceptionHandler;
use mgboot\exception\ExceptionHandlerImpl;
use mgboot\exception\HttpError;
use mgboot\exception\RequireAccessTokenException;
use mgboot\http\middleware\Middleware;
use mgboot\http\server\Request;
use mgboot\http\server\RequestHandler;
use mgboot\http\server\Response;
use mgboot\http\server\response\JsonResponse;
use mgboot\logger\NoopLogger;
use mgboot\mvc\MvcContext;
use mgboot\mvc\RouteRule;
use mgboot\security\CorsSettings;
use mgboot\security\JwtSettings;
use mgboot\util\StringUtils;
use Psr\Log\LoggerInterface;
use Throwable;

final class MgBoot
{
    private static string $controllerDir = '';
    private static ?LoggerInterface $runtimeLogger = null;
    private static bool $requestLogEnabled = false;
    private static ?LoggerInterface $requestLogLogger = null;
    private static bool $executeTimeLogEnabled = false;
    private static ?LoggerInterface $executeTimeLogLogger = null;
    private static ?CorsSettings $corsSettings = null;
    /**
     * @var JwtSettings[]
     */
    private static array $jwtSettings = [];
    private static bool $gzipOutputEnabled = true;

    /**
     * @var ExceptionHandler[]
     */
    private static array $exceptionHandlers = [];

    /**
     * @var Middleware[]
     */
    private static array $middlewares = [];

    private static ?DbConfig $dbConfig = null;
    private static ?RedisConfig $redisConfig = null;
    private static ?GobackendSettings $gobackendSettings = null;
    private static bool $isInAmpMode = false;

    private function __construct()
    {
    }

    public static function handleRequest(Request $request, Response $response): void
    {
        $response->withExceptionHandlers(self::$exceptionHandlers);

        if (self::$corsSettings instanceof CorsSettings) {
            $response->withCorsSettings(self::$corsSettings);
        }

        if (strtolower($request->getMethod()) === 'options') {
            $response->withPayload(JsonResponse::withPayload(['status' => 200]));

            if (!$request->inAmpMode()) {
                $response->sendByFpm();
            }

            return;
        }

        $dispatcher = MvcContext::getRouteDispatcher();

        if (!($dispatcher instanceof Dispatcher)) {
            $response->withPayload(HttpError::create(400));

            if (!$request->inAmpMode()) {
                $response->sendByFpm();
            }

            return;
        }

        $httpMethod = strtoupper($request->getMethod());
        $uri = $request->getRequestUrl();

        if (str_contains($uri, '?')) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }

        $uri = rawurldecode($uri);

        try {
            list($resultCode, $handlerFunc, $pathVariables) = $dispatcher->dispatch($httpMethod, $uri);

            switch ($resultCode) {
                case Dispatcher::NOT_FOUND:
                    $response->withPayload(HttpError::create(404));
                    break;
                case Dispatcher::METHOD_NOT_ALLOWED:
                    $response->withPayload(HttpError::create(405));
                    break;
                case Dispatcher::FOUND:
                    if (!is_string($handlerFunc) || $handlerFunc === '') {
                        $response->withPayload(HttpError::create(400));
                    } else if (!self::setRouteRuleToRequest($request, $httpMethod, $handlerFunc)) {
                        $response->withPayload(HttpError::create(400));
                    } else {
                        if (is_array($pathVariables) && !empty($pathVariables)) {
                            $request->withPathVariables($pathVariables);
                        }

                        RequestHandler::create($request, $response)->handleRequest(self::$middlewares);
                    }

                    break;
                default:
                    $response->withPayload(HttpError::create(400));
                    break;
            }
        } catch (Throwable $ex) {
            $response->withPayload($ex);
        }

        if (!$request->inAmpMode()) {
            $response->sendByFpm();
        }
    }

    public static function scanControllersIn(?string $dir = null): string
    {
        if (is_string($dir) && $dir !== '' && is_dir($dir)) {
            self::$controllerDir = $dir;
            return '';
        }

        return self::$controllerDir;
    }

    public static function setRuntimeLogger(LoggerInterface $logger): void
    {
        self::$runtimeLogger = $logger;
    }

    public static function getRuntimeLogger(): LoggerInterface
    {
        $logger = self::$runtimeLogger;
        return $logger instanceof LoggerInterface ? $logger : NoopLogger::create();
    }

    public static function enableRequestLog(LoggerInterface $logger): void
    {
        self::$requestLogEnabled = true;
        self::$requestLogLogger = $logger;
    }

    public static function isRequestLogEnabled(): bool
    {
        return self::$requestLogEnabled;
    }

    public static function getRequestLogLogger(): LoggerInterface
    {
        $logger = self::$requestLogLogger;
        return $logger instanceof LoggerInterface ? $logger : NoopLogger::create();
    }

    public static function enableExecuteTimeLog(LoggerInterface $logger): void
    {
        self::$executeTimeLogEnabled = true;
        self::$executeTimeLogLogger = $logger;
    }

    public static function isExecuteTimeLogEnabled(): bool
    {
        return self::$executeTimeLogEnabled;
    }

    public static function getExecuteTimeLogLogger(): LoggerInterface
    {
        $logger = self::$executeTimeLogLogger;
        return $logger instanceof LoggerInterface ? $logger : NoopLogger::create();
    }

    public static function withCorsSettings(CorsSettings $settings): void
    {
        self::$corsSettings = $settings;
    }

    public static function withJwtSettings(JwtSettings $settings): void
    {
        $idx = -1;

        foreach (self::$jwtSettings as $i => $item) {
            if ($item->getKey() === $settings->getKey()) {
                $idx = $i;
                break;
            }
        }

        if ($idx < 0) {
            self::$jwtSettings[] = $settings;
        } else {
            self::$jwtSettings[$idx] = $settings;
        }
    }

    public static function getJwtSettings(string $key): ?JwtSettings
    {
        foreach (self::$jwtSettings as $settings) {
            if ($settings->getKey() === $key) {
                return $settings;
            }
        }

        return null;
    }

    public static function disableGzipOutput(): void
    {
        self::$gzipOutputEnabled = false;
    }

    public static function isGzipOutputEnabled(): bool
    {
        return self::$gzipOutputEnabled;
    }

    public static function withExceptionHandler(ExceptionHandler $handler): void
    {
        self::checkNecessaryExceptionHandlers();
        $idx = -1;

        foreach (self::$exceptionHandlers as $i => $item) {
            if ($item->getExceptionClassName() === $handler->getExceptionClassName()) {
                $idx = $i;
                break;
            }
        }

        if ($idx < 0) {
            self::$exceptionHandlers[] = $handler;
        } else {
            self::$exceptionHandlers[$idx] = $handler;
        }
    }

    public static function withMiddleware(Middleware $middleware): void
    {
        if (self::isMiddlewaresExists(get_class($middleware))) {
            return;
        }

        self::$middlewares[] = $middleware;
    }

    public static function withDbConfig(array $settings): void
    {
        self::$dbConfig = DbConfig::create($settings);
    }

    public static function getDbConfig(): DbConfig
    {
        $cfg = self::$dbConfig;
        return $cfg === null ? DbConfig::create() : $cfg;
    }

    public static function withRedisConfig(array $settings): void
    {
        self::$redisConfig = RedisConfig::create($settings);
    }

    public static function getRedisConfig(): RedisConfig
    {
        $cfg = self::$redisConfig;
        return $cfg === null ? RedisConfig::create() : $cfg;
    }

    public static function withGobackendSettings(array $settings): void
    {
        self::$gobackendSettings = GobackendSettings::create($settings);
    }

    public static function getGobackendSettings(): GobackendSettings
    {
        $settings = self::$gobackendSettings;
        return $settings === null ? GobackendSettings::create() : $settings;
    }

    public static function inAmpMode(?bool $flag = null): bool
    {
        if (is_bool($flag)) {
            self::$isInAmpMode = $flag === true;
            return false;
        }

        return self::$isInAmpMode;
    }

    private static function checkNecessaryExceptionHandlers(): void
    {
        $classes = [
            AccessTokenExpiredException::class,
            AccessTokenInvalidException::class,
            DataValidateException::class,
            RequireAccessTokenException::class
        ];

        foreach ($classes as $clazz) {
            $found = false;

            foreach (self::$exceptionHandlers as $handler) {
                if (str_contains($handler->getExceptionClassName(), $clazz)) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                self::$exceptionHandlers[] = ExceptionHandlerImpl::create($clazz);
            }
        }
    }

    private static function isMiddlewaresExists(string $clazz): bool
    {
        $clazz = StringUtils::ensureLeft($clazz, "\\");

        foreach (self::$middlewares as $mid) {
            if (StringUtils::ensureLeft(get_class($mid), "\\") === $clazz) {
                return true;
            }
        }

        return false;
    }

    private static function setRouteRuleToRequest(Request $request, string $httpMethod, string $handlerFunc): bool
    {
        $routeRules = MvcContext::getRouteRules();
        $matched = null;

        foreach ($routeRules as $rule) {
            if ($rule->getHttpMethod() === $httpMethod && $rule->getHandler() === $handlerFunc) {
                $matched = $rule;
                break;
            }
        }

        if (!($matched instanceof RouteRule)) {
            foreach ($routeRules as $rule) {
                if ($rule->getHandler() === $handlerFunc && $rule->getHttpMethod() === '') {
                    $matched = $rule;
                    break;
                }
            }
        }

        if ($matched instanceof RouteRule) {
            $request->withRouteRule($matched);
            return true;
        }

        return false;
    }
}

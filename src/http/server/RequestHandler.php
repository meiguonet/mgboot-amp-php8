<?php

namespace mgboot\http\server;

use mgboot\exception\HttpError;
use mgboot\http\middleware\DataValidateMiddleware;
use mgboot\http\middleware\ExecuteTimeLogMiddleware;
use mgboot\http\middleware\JwtAuthMiddleware;
use mgboot\http\middleware\Middleware;
use mgboot\http\middleware\RequestLogMiddleware;
use mgboot\MgBoot;
use mgboot\mvc\HandlerFuncArgsInjector;
use mgboot\mvc\RoutingContext;
use mgboot\util\StringUtils;
use Throwable;

final class RequestHandler
{
    private Request $request;
    private Response $response;

    private function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    public static function create(Request $request, Response $response): self
    {
        return new self($request, $response);
    }

    /**
     * @param Middleware[] $middlewares
     */
    public function handleRequest(array $middlewares = []): void
    {
        $request = $this->request;
        $stages = [];

        foreach ($this->getPreHandleMiddlewares($request, $middlewares) as $mid) {
            $stages[] = fn(RoutingContext $rc) => $mid->preHandle($rc);
        }

        $routeRule = $request->getRouteRule();

        $stages[] = function (RoutingContext $rc) use ($routeRule) {
            if (!$rc->next()) {
                return;
            }

            list($clazz, $methodName) = explode('@', $routeRule->getHandler());
            $clazz = StringUtils::ensureLeft($clazz, "\\");

            try {
                $bean = new $clazz();
            } catch (Throwable) {
                $bean = null;
            }

            if (!is_object($bean)) {
                $rc->getResponse()->withPayload(HttpError::create(400));
                $rc->next(false);
                return;
            }

            if (!method_exists($bean, $methodName)) {
                $rc->getResponse()->withPayload(HttpError::create(400));
                $rc->next(false);
                return;
            }

            try {
                $args = HandlerFuncArgsInjector::inject($rc->getRequest());
            } catch (Throwable $ex) {
                $rc->getResponse()->withPayload($ex);
                $rc->next(false);
                return;
            }

            try {
                $payload = empty($args)
                    ? call_user_func([$bean, $methodName])
                    : call_user_func([$bean, $methodName], ...$args);

                $rc->getResponse()->withPayload($payload);
            } catch (Throwable $ex) {
                $rc->getResponse()->withPayload($ex);
                $rc->next(false);
            }
        };

        foreach ($this->getPostHandleMiddlewares($middlewares) as $mid) {
            $stages[] = fn(RoutingContext $rc) => $mid->preHandle($rc);
        }

        $response = $this->response;
        $ctx = RoutingContext::create($request, $response);

        foreach ($stages as $stage) {
            try {
                $stage($ctx);
            } catch (Throwable $ex) {
                $response->withPayload($ex);
                break;
            }
        }
    }

    /**
     * @param Request $req
     * @param Middleware[] $customMiddlewares
     * @return array
     */
    private function getPreHandleMiddlewares(Request $req, array $customMiddlewares = []): array
    {
        /* @var Middleware[] $middlewares */
        $middlewares = [];

        if (MgBoot::isRequestLogEnabled()) {
            $middlewares[] = RequestLogMiddleware::create();
        }

        $routeRule = $req->getRouteRule();

        if ($routeRule->getJwtSettingsKey() !== '') {
            $middlewares[] = JwtAuthMiddleware::create();
        }

        if (!empty($routeRule->getValidateRules())) {
            $middlewares[] = DataValidateMiddleware::create();
        }

        $customMiddlewares = array_filter($customMiddlewares, fn($it) => $it->getType() === Middleware::PRE_HANDLE_MIDDLEWARE);

        if (!empty($customMiddlewares)) {
            $customMiddlewares = array_values($customMiddlewares);
            array_push($middlewares, ...$customMiddlewares);
        }

        if (empty($middlewares)) {
            return [];
        }

        $middlewares = collect($middlewares)->sortBy(fn($it) => $it->getOrder(), SORT_NUMERIC);
        return array_values($middlewares->toArray());
    }

    /**
     * @param Middleware[] $customMiddlewares
     * @return array
     */
    private function getPostHandleMiddlewares(array $customMiddlewares = []): array
    {
        $middlewares = array_filter($customMiddlewares, fn($it) => $it->getType() === Middleware::POST_HANDLE_MIDDLEWARE);
        $middlewares = empty($middlewares) ? [] : array_values($middlewares);

        if (MgBoot::isExecuteTimeLogEnabled()) {
            $middlewares[] = ExecuteTimeLogMiddleware::create();
        }

        if (empty($middlewares)) {
            return [];
        }

        $middlewares = collect($middlewares)->sortBy(fn($it) => $it->getOrder(), SORT_NUMERIC);
        return array_values($middlewares->toArray());
    }
}

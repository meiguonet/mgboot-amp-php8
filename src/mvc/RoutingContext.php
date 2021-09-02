<?php

namespace mgboot\mvc;

use mgboot\exception\HttpError;
use mgboot\http\server\Request;
use mgboot\http\server\Response;
use Throwable;

final class RoutingContext
{
    private Request $request;
    private Response $response;
    private bool $hasNext = true;

    private function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    public static function create(Request $request, Response $response): self
    {
        return new self($request, $response);
    }

    public function next(?bool $arg0 = null): bool
    {
        if (is_bool($arg0)) {
            $this->hasNext = $arg0;
            return true;
        }

        return $this->hasNext;
    }

    public function hasError(): bool
    {
        $payload = $this->response->getPayload();
        return $payload instanceof Throwable || $payload instanceof HttpError;
    }

    /**
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }
}

<?php

namespace mgboot\http\middleware;

use mgboot\mvc\RoutingContext;

interface Middleware
{
    const PRE_HANDLE_MIDDLEWARE = 1;
    const POST_HANDLE_MIDDLEWARE = 2;
    const HIGHEST_ORDER = 1;
    const LOWEST_ORDER = 255;

    public function getType(): int;

    public function getOrder(): int;

    public function preHandle(RoutingContext $ctx): void;

    public function postHandle(RoutingContext $ctx): void;
}

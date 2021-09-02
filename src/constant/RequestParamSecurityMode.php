<?php

namespace mgboot\constant;

final class RequestParamSecurityMode
{
    const NONE = 0;
    const HTML_PURIFY = 1;
    const STRIP_TAGS = 2;

    private function __construct()
    {
    }
}

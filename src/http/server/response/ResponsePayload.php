<?php

namespace mgboot\http\server\response;

use mgboot\exception\HttpError;

interface ResponsePayload
{
    public function getContentType(): string;

    public function getContents(): string|HttpError;
}

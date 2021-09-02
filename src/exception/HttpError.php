<?php

namespace mgboot\exception;

final class HttpError
{
    private int $statusCode;

    private function __construct(int $statusCode)
    {
        if ($statusCode < 400) {
            $statusCode = 500;
        }

        $this->statusCode = $statusCode;
    }

    public static function create(int $statusCode): self
    {
        return new self($statusCode);
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

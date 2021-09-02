<?php

namespace mgboot\bo;

use mgboot\traits\MapAbleTrait;

final class GobackendSettings
{
    use MapAbleTrait;

    private bool $enabled = false;
    private string $host = '127.0.0.1';
    private int $port = -1;

    private function __construct(?array $data = null)
    {
        if (!empty($data)) {
            $this->fromMap($data);
        }
    }

    public static function create(?array $data = null): self
    {
        return new self($data);
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }
}

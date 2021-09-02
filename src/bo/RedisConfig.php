<?php

namespace mgboot\bo;

use mgboot\Cast;
use mgboot\traits\MapAbleTrait;

final class RedisConfig
{
    use MapAbleTrait;

    private bool $enabled = false;
    private string $host = '127.0.0.1';
    private int $port = 6379;
    private string $password = '';
    private int $database = 0;
    private int $readTimeout = -1;
    private ?array $cliSettings = null;

    private function __construct(array $data = null)
    {
        if (!empty($data)) {
            $this->fromMap($data);
        }
    }

    public static function create(array $data = null): self
    {
        if (is_array($data)) {
            if (is_string($data['read-timeout']) && $data['read-timeout'] !== '') {
                $data['read-timeout'] = Cast::toDuration($data['read-timeout']);
            }

            if (is_array($data['cli-mode'])) {
                $data['cliSettings'] = $data['cli-mode'];
                unset($data['cli-mode']);
            }
        }

        if (is_array($data) && is_array($data['cli-mode'])) {
            $data['cliSettings'] = $data['cli-mode'];
            unset($data['cli-mode']);
        }

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

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return int
     */
    public function getDatabase(): int
    {
        return $this->database;
    }

    /**
     * @return int
     */
    public function getReadTimeout(): int
    {
        return $this->readTimeout;
    }

    /**
     * @return array|null
     */
    public function getCliSettings(): ?array
    {
        return $this->cliSettings;
    }
}

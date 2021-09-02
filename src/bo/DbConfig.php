<?php

namespace mgboot\bo;

use mgboot\traits\MapAbleTrait;

final class DbConfig
{
    use MapAbleTrait;

    private bool $enabled = false;
    private string $host = '127.0.0.1';
    private int $port = 3306;
    private string $username = 'root';
    private string $password = '';
    private string $dbname = '';
    private string $charset = 'utf8mb4';
    private string $collation = 'utf8mb4_general_ci';
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
            if (is_string($data['database'])) {
                $data['dbname'] = $data['database'];
                unset($data['database']);
            }

            if (is_array($data['cli-mode'])) {
                $data['cliSettings'] = $data['cli-mode'];
                unset($data['cli-mode']);
            }
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
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getDbname(): string
    {
        return $this->dbname;
    }

    /**
     * @return string
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * @return string
     */
    public function getCollation(): string
    {
        return $this->collation;
    }

    /**
     * @return array|null
     */
    public function getCliSettings(): ?array
    {
        return $this->cliSettings;
    }
}

<?php

namespace mgboot\mvc;

use mgboot\traits\MapAbleTrait;

class HandlerFuncArgInfo
{
    use MapAbleTrait;

    private string $name = '';
    private string $type = '';
    private bool $nullable = false;
    private bool $request = false;
    private bool $jwt = false;
    private bool $clientIp = false;
    private string $httpHeaderName = '';
    private string $jwtClaimName = '';
    private string $pathVariableName = '';
    private array $requestParamInfo = [];
    private array $uploadedFileInfo = [];
    private bool $needRequestBody = false;
    private array $mapBindSettings = [];
    private array $dtoBindSettings = [];

    private function __construct(?array $data = null)
    {
        if (empty($data)) {
            return;
        }

        $this->fromMap($data);
    }

    public static function create(?array $data = null): self
    {
        return new self($data);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * @return bool
     */
    public function isRequest(): bool
    {
        return $this->request;
    }

    /**
     * @return bool
     */
    public function isJwt(): bool
    {
        return $this->jwt;
    }

    /**
     * @return bool
     */
    public function isClientIp(): bool
    {
        return $this->clientIp;
    }

    /**
     * @return string
     */
    public function getHttpHeaderName(): string
    {
        return $this->httpHeaderName;
    }

    /**
     * @return string
     */
    public function getJwtClaimName(): string
    {
        return $this->jwtClaimName;
    }

    /**
     * @return string
     */
    public function getPathVariableName(): string
    {
        return $this->pathVariableName;
    }

    /**
     * @return array
     */
    public function getRequestParamInfo(): array
    {
        return $this->requestParamInfo;
    }

    /**
     * @return array
     */
    public function getUploadedFileInfo(): array
    {
        return $this->uploadedFileInfo;
    }

    /**
     * @return bool
     */
    public function isNeedRequestBody(): bool
    {
        return $this->needRequestBody;
    }

    /**
     * @return array
     */
    public function getMapBindSettings(): array
    {
        return $this->mapBindSettings;
    }

    /**
     * @return array
     */
    public function getDtoBindSettings(): array
    {
        return $this->dtoBindSettings;
    }
}

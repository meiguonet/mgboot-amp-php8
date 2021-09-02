<?php

namespace mgboot\http\server;

use Amp\Http\Server\FormParser\File;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token;
use mgboot\Cast;
use mgboot\constant\Regexp;
use mgboot\constant\RequestParamSecurityMode as SecurityMode;
use mgboot\mvc\RouteRule;
use mgboot\HtmlPurifier;
use mgboot\util\ArrayUtils;
use mgboot\util\JwtUtils;
use mgboot\util\StringUtils;
use Amp\Http\Server\Request as AmpRequest;
use Throwable;
use function Amp\Http\Server\FormParser\parseForm;

final class Request
{

    private AmpRequest|null $ampRequest = null;
    private string $protocolVersion = '1.1';
    private string $httpMethod = 'GET';
    private array $headers = [];
    private array $queryParams = [];
    private array $formData = [];
    private array $pathVariables = [];
    private string $body = '';

    /**
     * @var UploadedFile[]
     */
    private array $uploadedFiles = [];

    private string|float $execStart;
    private ?Token $jwt = null;
    private array $serverParams = [];
    private array $cookieParams = [];
    private ?RouteRule $routeRule = null;

    private function __construct(?AmpRequest $request = null)
    {
        if ($request instanceof AmpRequest) {
            $this->ampRequest = $request;
        }

        $this->execStart = microtime(true);
        $this->buildServerParams();
        $this->buildCookieParams();
        $this->buildProtocolVersion();
        $this->buildHttpMethod();
        $this->buildHttpHeaders();
        $this->buildQueryParams();
        $this->buildFormData();
        $this->buildRawBody();
        $this->buildUploadedFiles();
        $this->buildJwt();
    }

    public static function create(?AmpRequest $request = null): self
    {
        return new self($request);
    }

    public function withRouteRule(RouteRule $rule): self
    {
        $this->routeRule = $rule;
        return $this;
    }

    public function withPathVariables(array $pathVariables): self
    {
        if (ArrayUtils::isAssocArray($pathVariables)) {
            $this->pathVariables = $pathVariables;
        }

        return $this;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): string
    {
        foreach ($this->headers as $headerName => $headerValue) {
            if (StringUtils::equals($headerName, $name, true)) {
                return $headerValue;
            }
        }

        return '';
    }

    public function getMethod(): string
    {
        return $this->httpMethod;
    }

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getServerParam(string $name): mixed
    {
       foreach ($this->serverParams as $key => $value) {
            if (StringUtils::equals($key, $name, true)) {
                return $value;
            }
        }

        return '';
    }

    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function getRawBody(): string
    {
        return $this->body;
    }

    public function getParsedBody(): array|object|null
    {
        $contentType = $this->headers['Content-Type'];

        if (stripos($contentType, 'application/x-www-form-urlencoded') !== false ||
            stripos($contentType, 'multipart/form-data') !== false) {
            return $this->formData;
        }

        $rawBody = $this->body;

        if ($rawBody === '') {
            return null;
        }

        if (stripos($contentType, 'application/json') !== false) {
            $data = json_decode($rawBody, true);
            return is_array($data) || is_object($data) ? $data : null;
        }

        if (stripos($contentType, 'application/xml') !== false || stripos($contentType, 'text/xml') !== false) {
            return StringUtils::xml2assocArray($rawBody);
        }

        return null;
    }

    public function getRequestUrl(bool $withQueryString = false): string
    {
        if ($this->inAmpMode()) {
            $requestUri = $this->ampRequest->getUri()->getPath();

            if (str_contains($requestUri, '?')) {
                $requestUri = StringUtils::substringBefore($requestUri, '?');
            }
        } else {
            $requestUri = $_SERVER['REQUEST_URI'];
        }

        if (!is_string($requestUri) || empty($requestUri)) {
            return '';
        }

        $requestUri = trim($requestUri, '/');
        $requestUri = StringUtils::ensureLeft($requestUri, '/');

        if (!$withQueryString) {
            return $requestUri;
        }

        $queryString = $this->getQueryString();
        return empty($queryString) ? $requestUri : "$requestUri?$queryString";
    }

    public function getQueryString(bool $urlencode = false): string
    {
        if (empty($this->queryParams)) {
            return '';
        }

        if ($urlencode) {
            return http_build_query($this->queryParams);
        }

        $sb = [];

        foreach ($this->queryParams as $key => $value) {
            $sb[] = "$key=$value";
        }

        return implode('&', $sb);
    }

    public function getClientIp(): string
    {
        $ip = $this->getHeader('X-Forwarded-For');

        if (empty($ip)) {
            $ip = $this->getHeader('X-Real-IP');
        }

        if (empty($ip)) {
            if ($this->inAmpMode()) {
                $ip = $this->ampRequest->getClient()->getRemoteAddress()->getHost();
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        }

        if (!is_string($ip) || empty($ip)) {
            return '';
        }

        $parts = preg_split(Regexp::COMMA_SEP, trim($ip));
        return is_array($parts) && !empty($parts) ? trim($parts[0]) : '';
    }

    public function getPathVariables(): array
    {
        return $this->pathVariables;
    }

    public function getFormData(): array
    {
        return $this->formData;
    }

    public function getRouteRule(): RouteRule
    {
        $rule = $this->routeRule;
        return $rule instanceof RouteRule ? $rule : RouteRule::create();
    }

    public function jwtIntCliam(string $name, int $default = PHP_INT_MIN): int
    {
        return $this->jwt === null ? $default : JwtUtils::intClaim($this->jwt, $name, $default);
    }

    public function jwtFloatClaim(string $name, float $default = PHP_FLOAT_MIN): float
    {
        return $this->jwt === null ? $default : JwtUtils::floatClaim($this->jwt, $name, $default);
    }

    public function jwtBooleanClaim(string $name, bool $default = false): bool
    {
        return $this->jwt === null ? $default : JwtUtils::booleanClaim($this->jwt, $name, $default);
    }

    public function jwtStringClaim(string $name, string $default = ''): string
    {
        return $this->jwt === null ? $default : JwtUtils::stringClaim($this->jwt, $name, $default);
    }

    public function jwtArrayClaim(string $name): array
    {
        return $this->jwt === null ? [] : JwtUtils::arrayClaim($this->jwt, $name);
    }

    public function pathVariableAsInt(string $name, int $default = PHP_INT_MIN): int
    {
        return Cast::toInt($this->pathVariables[$name], $default);
    }

    public function pathVariableAsFloat(string $name, float $default = PHP_FLOAT_MIN): float
    {
        return Cast::toFloat($this->pathVariables[$name], $default);
    }

    public function pathVariableAsBoolean(string $name, bool $default = false): bool
    {
        return Cast::toBoolean($this->pathVariables[$name], $default);
    }

    public function pathVariableAsString(string $name, string $default = ''): string
    {
        return Cast::toString($this->pathVariables[$name], $default);
    }

    public function requestParamAsInt(string $name, int $default = PHP_INT_MIN): int
    {
        $map1 = array_merge($this->queryParams, $this->formData);
        return Cast::toInt($map1[$name], $default);
    }

    public function requestParamAsFloat(string $name, float $default = PHP_FLOAT_MIN): float
    {
        $map1 = array_merge($this->queryParams, $this->formData);
        return Cast::toFloat($map1[$name], $default);
    }

    public function requestParamAsBoolean(string $name, bool $default = false): bool
    {
        $map1 = array_merge($this->queryParams, $this->formData);
        return Cast::toBoolean($map1[$name], $default);
    }

    public function requestParamAsString(string $name, int $securityMode = SecurityMode::STRIP_TAGS): string
    {
        $map1 = array_merge($this->queryParams, $this->formData);
        $value = Cast::toString($map1[$name]);

        return match ($securityMode) {
            SecurityMode::HTML_PURIFY => HtmlPurifier::purify($value),
            SecurityMode::STRIP_TAGS => strip_tags($value),
            default => $value,
        };
    }

    public function requestParamAsArray(string $name): array
    {
        $map1 = array_merge($this->queryParams, $this->formData);
        $ret = json_decode(Cast::toString($map1[$name]), true);
        return is_array($ret) ? $ret : [];
    }

    public function getRequestParams(string|array $rules): array
    {
        return ArrayUtils::requestParams(array_merge($this->queryParams, $this->formData), $rules);
    }

    public function getJwt(): ?Token
    {
        return $this->jwt;
    }

    public function getExecStart(): string|float
    {
        return $this->execStart;
    }

    public function inAmpMode(): bool
    {
        return $this->ampRequest instanceof AmpRequest;
    }

    private function buildProtocolVersion(): void
    {
        if ($this->ampRequest instanceof AmpRequest) {
            return;
        }

        $protocol = $_SERVER['SERVER_PROTOCOL'];

        if (!is_string($protocol) || empty($protocol)) {
            return;
        }

        $protocol = preg_replace('/[^0-9.]/', '', $protocol);
        $this->protocolVersion = strtolower($protocol);
    }

    private function buildHttpMethod(): void
    {
        if ($this->inAmpMode()) {
            /**
             * @var AmpRequest $req
             */
            $req = $this->ampRequest;

            $this->httpMethod = strtoupper($req->getMethod());
            return;
        }

        $this->httpMethod = strtoupper($_SERVER['REQUEST_METHOD']);
    }

    private function buildHttpHeaders(): void
    {
        if ($this->inAmpMode()) {
            /**
             * @var AmpRequest $req
             */
            $req = $this->ampRequest;
            $map1 = [];

            foreach ($req->getHeaders() as $headerName => $headerValues) {
                if (!is_string($headerName) || $headerName === '') {
                    continue;
                }

                if (is_string($headerValues) && $headerValues !== '') {
                    $map1[$headerName] = $headerValues;
                    continue;
                }

                if (is_array($headerValues) && !empty($headerValues)) {
                    $map1[$headerName] = implode('; ', $headerValues);
                }
            }
        } else {
            $map1 = $_SERVER;
        }

        if (!is_array($map1) || empty($map1)) {
            return;
        }

        foreach ($map1 as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            $key = strtolower($key);

            if (str_starts_with($key, 'http_')) {
                $headerName = substr($key, 5);
            } else if (stripos($key, 'PHP_AUTH_DIGEST') !== false) {
                $headerName = 'authorization';
            } else {
                $headerName = $this->inAmpMode() ? $key : '';
            }

            if (empty($headerName)) {
                continue;
            }

            $headerName = preg_replace('/[\x20\t_-]+/', ' ', trim($headerName));
            $headerName = str_replace(' ', '-', ucwords($headerName));
            $this->headers[$headerName] = $value;
        }
    }

    private function buildQueryParams(): void
    {
        if ($this->inAmpMode()) {
            /**
             * @var AmpRequest $req
             */
            $req = $this->ampRequest;
            $map1 = [];
            $qs = $req->getUri()->getQuery();
            $qs = is_string($qs) ? ltrim($qs, '?') : '';

            foreach (explode('&', $qs) as $s1) {
                $parts = explode('=', $s1);
                $n1 = count($parts);

                if ($n1 < 1) {
                    continue;
                }

                if ($n1 === 1) {
                    $map1[$parts[0]] = '';
                    continue;
                }

                $map1[$parts[0]] = urldecode($parts[1]);
            }
        } else {
            $map1 = $_GET;
        }

        if (!is_array($map1) || empty($map1)) {
            return;
        }

        foreach ($map1 as $key => $value) {
            if (!is_string($key) || empty($key)) {
                continue;
            }

            $this->queryParams[$key] = Cast::toString($value);
        }
    }

    private function buildFormData(): void
    {
        if ($this->inAmpMode()) {
            $map1 = [];

            foreach ($this->buildFormDataByAmpRequest() as $part) {
                list($fieldName, $fieldValue) = $part;
                $map1[$fieldName] = $fieldValue;
            }
        } else {
            $map1 = $_POST;
        }

        if (!is_array($map1) || empty($map1)) {
            return;
        }

        foreach ($map1 as $key => $value) {
            if (!is_string($key) || empty($key)) {
                continue;
            }

            $this->formData[$key] = Cast::toString($value);
        }
    }

    private function buildFormDataByAmpRequest(): iterable
    {
        $contentType = $this->getHeader('Content-Type');

        if (stripos($contentType, 'application/x-www-form-urlencoded') === false &&
            stripos($contentType, 'multipart/form-data') === false) {
            return [];
        }

        /**
         * @var AmpRequest $req
         */
        $req = $this->ampRequest;
        $form = yield parseForm($req);

        if (!is_object($form) || !method_exists($form, 'getValues')) {
            return [];
        }

        $values = $form->getValues();

        if (!is_array($values) || empty($values)) {
            return [];
        }

        $parts = [];

        foreach ($values as $fieldName => $fieldValues) {
            if (!is_string($fieldName) || $fieldName === '') {
                continue;
            }

            if (is_string($fieldValues)) {
                $parts[] = [$fieldName, $fieldValues];
                continue;
            }

            if (is_array($fieldValues)) {
                $parts[] = [$fieldName, empty($fieldValues) ? '' : implode('; ', $fieldValues)];
            }
        }

        return $parts;
    }

    private function buildRawBody(): void
    {
        $contentType = $this->getHeader('Content-Type');

        if (stripos($contentType, 'application/x-www-form-urlencoded') !== false ||
            stripos($contentType, 'multipart/form-data') !== false) {
            $this->body = empty($this->formData) ? '' : http_build_query($this->formData);
            return;
        }

        if (stripos($contentType, 'application/json') !== false ||
            stripos($contentType, 'application/xml') !== false ||
            stripos($contentType, 'text/xml') !== false) {
            $contents = '';

            foreach ($this->readRawBodyContents() as $s1) {
                $contents = $s1;
                break;
            }

            $this->body = is_string($contents) ? $contents : '';
        }
    }

    private function readRawBodyContents(): iterable
    {
        if ($this->inAmpMode()) {
            /**
             * @var AmpRequest $req
             */
            $req = $this->ampRequest;
            $contents = $req->getBody()->read();
            return [is_string($contents) ? $contents : ''];
        }

        return [Cast::toString(file_get_contents('php://input'))];
    }

    private function buildUploadedFiles(): void
    {
        if ($this->inAmpMode()) {
            foreach ($this->getFilePartsByAmpRequest() as $part) {
                /**
                 * @var File $fileItem
                 */
                list($formFieldName, $fileItem) = $part;

                if (!($fileItem instanceof File)) {
                    continue;
                }

                $buf = $fileItem->getContents();

                if ($buf === '') {
                    $this->uploadedFiles[$formFieldName] = UploadedFile::create($formFieldName, [
                        'error' => UPLOAD_ERR_NO_FILE
                    ]);

                    continue;
                }

                $this->uploadedFiles[$formFieldName] = UploadedFile::create($formFieldName, [
                    'name' => $fileItem->getName(),
                    'type' => $fileItem->getMimeType(),
                    'size' => strlen($buf),
                    'buf' => $buf
                ]);
            }

            return;
        }

        foreach ($_FILES as $formFieldName => $fileItem) {
            if (!is_string($formFieldName) || empty($formFieldName) || !is_array($fileItem) || empty($fileItem)) {
                continue;
            }

            $name = Cast::toString($fileItem['name']);

            if (empty($name)) {
                continue;
            }

            $meta = [
                'name' => $name,
                'type' => Cast::toString($fileItem['type']),
                'size' => Cast::toInt($fileItem['size']),
                'tmp_name' => Cast::toString($fileItem['tmp_name']),
                'error' => Cast::toInt($fileItem['error'])
            ];

            $this->uploadedFiles[$formFieldName] = UploadedFile::create($formFieldName, $meta);
        }
    }

    private function getFilePartsByAmpRequest(): iterable
    {
        $contentType = $this->getHeader('Content-Type');

        if (stripos($contentType, 'multipart/form-data') === false) {
            return [];
        }

        /**
         * @var AmpRequest $req
         */
        $req = $this->ampRequest;
        $form = yield parseForm($req);

        if (!is_object($form) || !method_exists($form, 'getFiles')) {
            return [];
        }

        $files = $form->getFiles();

        if (!is_array($files) || empty($files)) {
            return [];
        }

        $parts = [];

        foreach ($files as $formFieldName => $items) {
            if (!is_string($formFieldName) || $formFieldName === '') {
                continue;
            }

            if (!is_array($items) || empty($items)) {
                continue;
            }

            $parts[] = [$formFieldName, $items[0]];
        }

        return $parts;
    }

    private function buildJwt(): void
    {
        $token = $this->getHeader('Authorization');

        if (!is_string($token) || $token === '') {
            $this->jwt = null;
            return;
        }

        $token = preg_replace('/[\x20\t]+/', ' ', trim($token));

        if (str_contains($token, ' ')) {
            $token = StringUtils::substringAfterLast($token, ' ');
        }

        try {
            $jwt = (new Token\Parser(new JoseEncoder()))->parse($token);
        } catch (Throwable) {
            $jwt = null;
        }

        $this->jwt = $jwt;
    }

    private function buildServerParams(): void
    {
        if ($this->inAmpMode()) {
            /**
             * @var AmpRequest $req
             */
            $req = $this->ampRequest;

            $map1 = [
                'REQUEST_METHOD' => strtoupper($req->getMethod()),
                'QUERY_STRING' => ltrim($req->getUri()->getQuery(), '?'),
                'REMOTE_ADDR' => $req->getClient()->getRemoteAddress()->getHost()
            ];
        } else {
            $map1 = $_SERVER;
        }

        if (!is_array($map1) || empty($map1)) {
            return;
        }

        foreach ($map1 as $key => $value) {
            if (!is_string($key) || empty($key)) {
                continue;
            }

            $this->serverParams[strtolower($key)] = Cast::toString($value);
        }
    }

    private function buildCookieParams(): void
    {
        if ($this->inAmpMode()) {
            /**
             * @var AmpRequest $req
             */
            $req = $this->ampRequest;

            $map1 = [];

            foreach ($req->getCookies() as $cookie) {
                $map1[$cookie->getName()] = $cookie->getValue();
            }
        } else {
            $map1 = $_COOKIE;
        }

        if (!is_array($map1) || empty($map1)) {
            return;
        }

        foreach ($map1 as $key => $value) {
            if (!is_string($key) || empty($key)) {
                continue;
            }

            $this->cookieParams[$key] = Cast::toString($value);
        }
    }
}

<?php

namespace mgboot\mvc;

use Lcobucci\JWT\Token;
use mgboot\Cast;
use mgboot\http\server\Request;
use mgboot\http\server\UploadedFile;
use mgboot\util\ArrayUtils;
use mgboot\util\JsonUtils;
use mgboot\util\ReflectUtils;
use mgboot\util\StringUtils;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use RuntimeException;
use stdClass;
use Throwable;

final class HandlerFuncArgsInjector
{
    private static string $fmt1 = 'fail to inject arg for handler function %s, name: %s, type: %s';

    private function __construct()
    {
    }

    public static function inject(Request $req): array
    {
        $routeRule = $req->getRouteRule();
        $handler = $routeRule->getHandler();
        $args = [];

        foreach ($routeRule->getHandlerFuncArgs() as $info) {
            if ($info->isRequest()) {
                $args[] = $req;
                continue;
            }

            if ($info->isJwt()) {
                $jwt = $req->getJwt();

                if (!($jwt instanceof Token) && !$info->isNullable()) {
                    self::thowException($handler, $info);
                }

                $args[] = $jwt;
                continue;
            }

            if ($info->isClientIp()) {
                $args[] = $req->getClientIp();
                continue;
            }

            if ($info->getHttpHeaderName() !== '') {
                $args[] = $req->getHeader($info->getHttpHeaderName());
                continue;
            }

            if ($info->getJwtClaimName() !== '') {
                self::bindJwtClaim($req, $args, $info);
                continue;
            }

            if ($info->getPathVariableName() !== '') {
                self::bindPathVariable($req, $args, $info);
                continue;
            }

            if (!empty($info->getRequestParamInfo())) {
                self::bindRequestParam($req, $args, $info);
                continue;
            }

            if (!empty($info->getMapBindSettings())) {
                self::bindMap($req, $args, $info);
                continue;
            }

            if (!empty($info->getUploadedFileInfo())) {
                self::bindUploadedFile($req, $args, $info);
                continue;
            }

            if ($info->isNeedRequestBody()) {
                self::bindRequestBody($req, $args, $info);
                continue;
            }

            if (!empty($info->getDtoBindSettings())) {
                self::bindDto($req, $args, $info);
                continue;
            }

            self::thowException($handler, $info);
        }

        return $args;
    }

    private static function bindJwtClaim(Request $req, array &$args, HandlerFuncArgInfo $info): void
    {
        $claimName = $info->getJwtClaimName();

        switch ($info->getType()) {
            case 'int':
                $args[] = $req->jwtIntCliam($claimName);
                break;
            case 'float':
                $args[] = $req->jwtFloatClaim($claimName);
                break;
            case 'bool':
                $args[] = $req->jwtBooleanClaim($claimName);
                break;
            case 'string':
                $args[] = $req->jwtStringClaim($claimName);
                break;
            case 'array':
                $args[] = $req->jwtArrayClaim($claimName);
                break;
            default:
                if ($info->isNullable()) {
                    $args[] = null;
                } else {
                    $fmt = '@@fmt:' . self::$fmt1 . ', reason: unsupported jwt claim type [%s]';
                    $handler = $req->getRouteRule()->getHandler();
                    self::thowException($handler, $info, $fmt, $info->getType());
                }

                break;
        }
    }

    private static function bindPathVariable(Request $req, array &$args, HandlerFuncArgInfo $info): void
    {
        $name = $info->getPathVariableName();

        switch ($info->getType()) {
            case 'int':
                $args[] = $req->pathVariableAsInt($name);
                break;
            case 'float':
                $args[] = $req->pathVariableAsFloat($name);
                break;
            case 'bool':
                $args[] = $req->pathVariableAsBoolean($name);
                break;
            case 'string':
                $args[] = $req->pathVariableAsString($name);
                break;
            default:
                if ($info->isNullable()) {
                    $args[] = null;
                } else {
                    $fmt = '@@fmt:' . self::$fmt1 . ', reason: unsupported path variable type [%s]';
                    $handler = $req->getRouteRule()->getHandler();
                    self::thowException($handler, $info, $fmt, $info->getType());
                }

                break;
        }
    }

    private static function bindRequestParam(Request $req, array &$args, HandlerFuncArgInfo $info): void
    {
        $map1 = $info->getRequestParamInfo();
        $name = $map1['name'];

        switch ($info->getType()) {
            case 'int':
                $args[] = $req->requestParamAsInt($name);
                break;
            case 'float':
                $args[] = $req->requestParamAsFloat($name);
                break;
            case 'bool':
                $args[] = $req->requestParamAsBoolean($name);
                break;
            case 'string':
                if ($map1['decimal']) {
                    $args = bcadd(trim($req->requestParamAsString($name)), 0, 2);
                } else {
                    $securityMode = Cast::toInt($map1['securityMode']);
                    $args[] = trim($req->requestParamAsString($name, $securityMode));
                }

                break;
            case 'array':
                $args[] = $req->requestParamAsArray($name);
                break;
            default:
                if ($info->isNullable()) {
                    $args[] = null;
                } else {
                    $fmt = '@@fmt:' . self::$fmt1 . ', reason: unsupported request param type [%s]';
                    $handler = $req->getRouteRule()->getHandler();
                    self::thowException($handler, $info, $fmt, $info->getType());
                }

                break;
        }
    }

    private static function bindMap(Request $req, array &$args, HandlerFuncArgInfo $info): void
    {
        $handler = $req->getRouteRule()->getHandler();

        if ($info->getType() !== 'array') {
            if ($info->isNullable()) {
                $args[] = null;
                return;
            }

            self::thowException($handler, $info);
        }

        $isGet = strtoupper($req->getMethod()) === 'GET';
        $contentType = $req->getHeader('Content-Type');
        $isJsonPayload = stripos($contentType, 'application/json') !== false;

        $isXmlPayload = stripos($contentType, 'application/xml') !== false ||
            stripos($contentType, 'text/xml') !== false;

        if ($isGet) {
            $map1 = $req->getQueryParams();
        } else if ($isJsonPayload) {
            $map1 = JsonUtils::mapFrom($req->getRawBody());
        } else if ($isXmlPayload) {
            $map1 = StringUtils::xml2assocArray($req->getRawBody());
        } else {
            $map1 = array_merge($req->getQueryParams(), $req->getFormData());
        }

        if (!is_array($map1)) {
            if ($info->isNullable()) {
                $args[] = null;
                return;
            }

            self::thowException($handler, $info);
        }

        foreach ($map1 as $key => $val) {
            if (!is_string($val) || is_numeric($val)) {
                continue;
            }

            $map1[$key] = trim($val);
        }

        $settings = $info->getMapBindSettings();
        $args[] = empty($settings['rules']) ? $map1 : ArrayUtils::requestParams($map1, $settings['rules']);
    }

    private static function bindUploadedFile(Request $req, array &$args, HandlerFuncArgInfo $info): void
    {
        $formFieldName = $info->getUploadedFileInfo()['formFieldName'];

        try {
            $uploadFile = $req->getUploadedFiles()[$formFieldName];
        } catch (Throwable) {
            $uploadFile = null;
        }

        if (!($uploadFile instanceof UploadedFile)) {
            if ($info->isNullable()) {
                $args[] = null;
                return;
            }

            $handler = $req->getRouteRule()->getHandler();
            self::thowException($handler, $info);
        }

        $args[] = $uploadFile;
    }

    private static function bindRequestBody(Request $req, array &$args, HandlerFuncArgInfo $info): void
    {
        if ($info->getType() !== 'string') {
            if ($info->isNullable()) {
                $args[] = null;
                return;
            }

            $handler = $req->getRouteRule()->getHandler();
            self::thowException($handler, $info);
        }

        $payload = $req->getRawBody();
        $map1 = JsonUtils::mapFrom($payload);

        if (is_array($map1) && ArrayUtils::isAssocArray($map1)) {
            foreach ($map1 as $key => $val) {
                if (!is_string($val) || is_numeric($val)) {
                    continue;
                }

                $map1[$key] = trim($val);
            }

            $payload = JsonUtils::toJson(empty($map1) ? new stdClass() : $map1);
        }

        $args[] = $payload;
    }

    private static function bindDto(Request $req, array &$args, HandlerFuncArgInfo $info): void
    {
        $handler = $req->getRouteRule()->getHandler();
        $fmt = '@@fmt:' . self::$fmt1 . ', reason: %s';
        $isGet = strtoupper($req->getMethod()) === 'GET';
        $contentType = $req->getHeader('Content-Type');
        $isJsonPayload = stripos($contentType, 'application/json') !== false;

        $isXmlPayload = stripos($contentType, 'application/xml') !== false ||
            stripos($contentType, 'text/xml') !== false;

        if ($isGet) {
            $map1 = $req->getQueryParams();
        } else if ($isJsonPayload) {
            $map1 = JsonUtils::mapFrom($req->getRawBody());
        } else if ($isXmlPayload) {
            $map1 = StringUtils::xml2assocArray($req->getRawBody());
        } else {
            $map1 = array_merge($req->getQueryParams(), $req->getFormData());
        }

        if (!is_array($map1)) {
            $map1 = [];
        }

        $settings = $info->getDtoBindSettings();
        $className = $settings['dtoClassName'];

        try {
            $clazz = new ReflectionClass($className);
            $bean = new $className();
        } catch (Throwable) {
            $clazz = null;
            $bean = null;
        }

        if (!($clazz instanceof ReflectionClass) || !is_object($bean)) {
            if ($info->isNullable()) {
                $args[] = null;
                return;
            }

            self::thowException($handler, $info, $fmt, '无法实例化 dto 对象');
        }

        try {
            $methods = $clazz->getMethods(ReflectionMethod::IS_PUBLIC);
        } catch (Throwable $ex) {
            $methods = [];
            self::thowException($handler, $info, $fmt, $ex->getMessage());
        }

        try {
            $fields = $clazz->getProperties();
        } catch (Throwable $ex) {
            $fields = [];
            self::thowException($handler, $info, $fmt, $ex->getMessage());
        }

        foreach ($fields as $field) {
            $setter = ReflectUtils::getSetter($field, $methods);

            if (!($setter instanceof ReflectionMethod)) {
                continue;
            }

            $fieldType = $field->getType();

            if ($fieldType instanceof ReflectionType) {
                $nullbale = $fieldType->allowsNull();

                if ($fieldType instanceof ReflectionNamedType) {
                    if ($fieldType->isBuiltin()) {
                        $fieldType = strtolower(str_replace('?', '', $fieldType->getName()));
                    } else {
                        $fieldType = StringUtils::ensureLeft(str_replace('?', '', $fieldType->getName()), "\\");
                    }
                } else {
                    $fieldType = 'unknow';
                }
            } else {
                $nullbale = true;
                $fieldType = 'unknow';
            }

            $fieldName = $field->getName();
            $fieldSettings = $settings[$fieldName] ?? [];

            if ($fieldSettings['clientIp']) {
                if ($fieldType !== 'string') {
                    $errorTips = "fail to set value to field[name=$fieldName, type=$fieldType] with string value";
                    self::thowException($handler, $info, $fmt, $errorTips);
                    continue;
                }

                try {
                    $setter->invoke($bean, $req->getClientIp());
                } catch (Throwable $ex) {
                    self::thowException($handler, $info, $fmt, $ex->getMessage());
                }

                continue;
            }

            if (is_string($fieldSettings['httpHeaderName'])) {
                if ($fieldType !== 'string') {
                    $errorTips = "fail to set value to field[name=$fieldName, type=$fieldType] with string value";
                    self::thowException($handler, $info, $fmt, $errorTips);
                    continue;
                }

                try {
                    $setter->invoke($bean, $req->getHeader($fieldSettings['httpHeaderName']));
                } catch (Throwable $ex) {
                    self::thowException($handler, $info, $fmt, $ex->getMessage());
                }

                continue;
            }

            if (is_string($fieldSettings['jwtClaimName'])) {
                $claimName = $fieldSettings['jwtClaimName'];
                $defaultValue = $fieldSettings['defaultValue'];

                switch ($fieldType) {
                    case 'int':
                        $defaultValue = is_int($defaultValue) ? $defaultValue : PHP_INT_MIN;
                        $fieldValue = $req->jwtIntCliam($claimName, $defaultValue);
                        break;
                    case 'float':
                        if (is_float($defaultValue) || is_int($defaultValue)) {
                            $defaultValue = floatval($defaultValue);
                        } else {
                            $defaultValue = PHP_FLOAT_MIN;
                        }

                        $fieldValue = $req->jwtFloatClaim($claimName, $defaultValue);
                        break;
                    case 'boolean':
                    case 'bool':
                        if (is_bool($defaultValue)) {
                            $defaultValue = $defaultValue === true;
                        } else {
                            $defaultValue = false;
                        }

                        $fieldValue = $req->jwtBooleanClaim($claimName, $defaultValue);
                        break;
                    case 'string':
                        if (is_string($defaultValue)) {
                            $defaultValue = "$defaultValue";
                        } else {
                            $defaultValue = '';
                        }

                        $fieldValue = $req->jwtStringClaim($claimName, $defaultValue);
                        break;
                    case 'array':
                        $fieldValue = $req->jwtArrayClaim($claimName);
                        break;
                    default:
                        $fieldValue = null;
                        break;
                }

                if ($fieldValue === null && !$nullbale) {
                    $errorTips = "fail to set value to field[name=$fieldName, type=$fieldType] with null value";
                    self::thowException($handler, $info, $fmt, $errorTips);
                    continue;
                }

                try {
                    $setter->invoke($bean, $req->getHeader($fieldSettings['httpHeaderName']));
                } catch (Throwable $ex) {
                    self::thowException($handler, $info, $fmt, $ex->getMessage());
                }

                continue;
            }

            if ($fieldSettings['needRequestBody']) {
                if ($fieldType !== 'string') {
                    $errorTips = "fail to set value to field[name=$fieldName, type=$fieldType] with string value";
                    self::thowException($handler, $info, $fmt, $errorTips);
                    continue;
                }

                try {
                    $setter->invoke($bean, $req->getRawBody());
                } catch (Throwable $ex) {
                    self::thowException($handler, $info, $fmt, $ex->getMessage());
                }

                continue;
            }

            if (is_string($fieldSettings['formFieldName'])) {
                $formFieldName = $fieldSettings['formFieldName'];
                $valueType = UploadedFile::class;
                $uploadedFile = $req->getUploadedFiles()[$formFieldName];

                if (!($uploadedFile instanceof UploadedFile) && !$nullbale) {
                    $errorTips = "fail to set value to field[name=$fieldName, type=$fieldType] with null value";
                    self::thowException($handler, $info, $fmt, $errorTips);
                    continue;
                }

                if (!str_contains($fieldType, $valueType)) {
                    $errorTips = "fail to set value to field[name=$fieldName, type=$fieldType] with $valueType instance";
                    self::thowException($handler, $info, $fmt, $errorTips);
                    continue;
                }

                try {
                    $setter->invoke($bean, $uploadedFile);
                } catch (Throwable $ex) {
                    self::thowException($handler, $info, $fmt, $ex->getMessage());
                }

                continue;
            }

            $mapKey = $fieldSettings['mapKey'];

            if (empty($mapKey)) {
                $mapKey = $fieldName;
            }

            $mapKey = strtr(strtolower($mapKey), ['-' => '', '_' => '']);
            $mapValue = null;

            foreach ($map1 as $key => $value) {
                $key = strtr(strtolower($key), ['-' => '', '_' => '']);

                if ($key === $mapKey) {
                    $mapValue = $value;
                    break;
                }
            }

            if ($mapValue === null) {
                $defaultValue = $fieldSettings['defaultValue'];

                switch ($fieldType) {
                    case 'int':
                        $defaultValue = is_int($defaultValue) ? $defaultValue : null;
                        $mapValue = $defaultValue;
                        break;
                    case 'float':
                        if (is_float($defaultValue) || is_int($defaultValue)) {
                            $defaultValue = floatval($defaultValue);
                        } else {
                            $defaultValue = null;
                        }

                        $mapValue = $defaultValue;
                        break;
                    case 'boolean':
                    case 'bool':
                        if (is_bool($defaultValue)) {
                            $defaultValue = $defaultValue === true;
                        } else {
                            $defaultValue = null;
                        }

                        $mapValue =$defaultValue;
                        break;
                    case 'string':
                        if (is_string($defaultValue)) {
                            $defaultValue = "$defaultValue";
                        } else {
                            $defaultValue = null;
                        }

                        $mapValue = $defaultValue;
                        break;
                }
            }

            if ($mapValue === null && !$nullbale) {
                $errorTips = "fail to set value to field[name=$fieldName, type=$fieldType] with null value";
                self::thowException($handler, $info, $fmt, $errorTips);
                continue;
            }

            $defaultValue = $fieldSettings['defaultValue'];

            switch ($fieldType) {
                case 'int':
                    $defaultValue = is_int($defaultValue) ? $defaultValue : PHP_INT_MIN;
                    $mapValue = Cast::toInt($mapValue, $defaultValue);
                    break;
                case 'float':
                    if (is_float($defaultValue) || is_int($defaultValue)) {
                        $defaultValue = floatval($defaultValue);
                    } else {
                        $defaultValue = PHP_FLOAT_MIN;
                    }

                    $mapValue = Cast::toFloat($mapValue, $defaultValue);
                    break;
                case 'boolean':
                case 'bool':
                    if (is_bool($defaultValue)) {
                        $defaultValue = $defaultValue === true;
                    } else {
                        $defaultValue = false;
                    }

                    $mapValue = Cast::toBoolean($mapValue, $defaultValue);
                    break;
                case 'string':
                    if (is_string($defaultValue)) {
                        $defaultValue = "$defaultValue";
                    } else {
                        $defaultValue = '';
                    }

                    $mapValue = Cast::toString($mapValue, $defaultValue);
                    break;
            }

            try {
                $setter->invoke($bean, $mapValue);
            } catch (Throwable $ex) {
                self::thowException($handler, $info, $fmt, $ex->getMessage());
            }
        }

        $args[] = $bean;
    }

    private static function thowException(string $handler, HandlerFuncArgInfo $info, mixed... $args): void
    {
        $fmt = self::$fmt1;
        $params = [$handler, $info->getName(), $info->getType()];

        if (!empty($args)) {
            if (is_string($args[0]) && str_starts_with($args[0], '@@fmt:')) {
                $fmt = str_replace('@@fmt:', '', array_shift($args));

                if (!empty($args)) {
                    array_push($params, ...$args);
                }
            } else {
                array_push($params, ...$args);
            }
        }

        $errorTips = sprintf($fmt, ...$params);
        throw new RuntimeException($errorTips);
    }
}

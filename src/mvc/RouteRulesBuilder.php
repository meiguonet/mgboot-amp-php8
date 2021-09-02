<?php

namespace mgboot\mvc;

use Lcobucci\JWT\Token;
use mgboot\annotation\BindingDefault;
use mgboot\annotation\ClientIp;
use mgboot\annotation\DeleteMapping;
use mgboot\annotation\DtoBind;
use mgboot\annotation\GetMapping;
use mgboot\annotation\HttpHeader;
use mgboot\annotation\JwtAuth;
use mgboot\annotation\JwtClaim;
use mgboot\annotation\MapBind;
use mgboot\annotation\MapKey;
use mgboot\annotation\PatchMapping;
use mgboot\annotation\PathVariable;
use mgboot\annotation\PostMapping;
use mgboot\annotation\PutMapping;
use mgboot\annotation\RequestBody;
use mgboot\annotation\RequestMapping;
use mgboot\annotation\RequestParam;
use mgboot\annotation\UploadedFile;
use mgboot\annotation\Validate;
use mgboot\Cast;
use mgboot\http\server\Request;
use mgboot\MgBoot;
use mgboot\util\FileUtils;
use mgboot\util\ReflectUtils;
use mgboot\util\StringUtils;
use mgboot\util\TokenizeUtils;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Stringable;
use Throwable;

final class RouteRulesBuilder
{
    private function __construct()
    {
    }

    /**
     * @return RouteRule[]
     */
    public static function buildRouteRules(): array
    {
        $dir = MgBoot::scanControllersIn();

        if ($dir === '' || !is_dir($dir)) {
            return [];
        }

        $files = [];
        FileUtils::scanFiles($dir, $files);
        $rules = [];

        foreach ($files as $fpath) {
            if (!preg_match('/\.php$/', $fpath)) {
                continue;
            }

            try {
                $tokens = token_get_all(file_get_contents($fpath));
                $className = TokenizeUtils::getQualifiedClassName($tokens);
                $clazz = new ReflectionClass($className);
            } catch (Throwable) {
                $className = '';
                $clazz = null;
            }

            if (empty($className) || !($clazz instanceof ReflectionClass)) {
                continue;
            }

            $anno1 = ReflectUtils::getClassAnnotation($clazz, RequestMapping::class);

            try {
                $methods = $clazz->getMethods(ReflectionMethod::IS_PUBLIC);
            } catch (Throwable) {
                $methods = [];
            }

            foreach ($methods as $method) {
                try {
                    $map1 = array_merge(
                        [
                            'handler' => "$className@{$method->getName()}",
                            'handlerFuncArgs' => self::buildHandlerFuncArgs($method)
                        ],
                        self::buildValidateRules($method),
                        self::buildJwtAuthSettings($method),
                        self::buildExtraAnnotations($method)
                    );
                } catch (Throwable) {
                    continue;
                }

                $rule = self::buildRouteRule(GetMapping::class, $method, $anno1, $map1);

                if ($rule instanceof RouteRule) {
                    $rules[] = $rule;
                    continue;
                }

                $rule = self::buildRouteRule(PostMapping::class, $method, $anno1, $map1);

                if ($rule instanceof RouteRule) {
                    $rules[] = $rule;
                    continue;
                }

                $rule = self::buildRouteRule(PutMapping::class, $method, $anno1, $map1);

                if ($rule instanceof RouteRule) {
                    $rules[] = $rule;
                    continue;
                }

                $rule = self::buildRouteRule(PatchMapping::class, $method, $anno1, $map1);

                if ($rule instanceof RouteRule) {
                    $rules[] = $rule;
                    continue;
                }

                $rule = self::buildRouteRule(DeleteMapping::class, $method, $anno1, $map1);

                if ($rule instanceof RouteRule) {
                    $rules[] = $rule;
                    continue;
                }

                $items = self::buildRouteRulesForRequestMapping($method, $anno1, $map1);

                if (!empty($items)) {
                    array_push($rules, ...$items);
                }
            }
        }

        return $rules;
    }

    private static function buildRouteRule(
        string $clazz,
        ReflectionMethod $method,
        mixed $anno,
        array $data
    ): ?RouteRule
    {
        $httpMethod = match (StringUtils::substringAfterLast($clazz, "\\")) {
            'GetMapping' => 'GET',
            'PostMapping' => 'POST',
            'PutMapping' => 'PUT',
            'PatchMapping' => 'PATCH',
            'DeleteMapping' => 'DELETE',
            default => ''
        };

        if ($httpMethod === '') {
            return null;
        }

        try {
            $newAnno =  ReflectUtils::getMethodAnnotation($method, $clazz);

            if (!is_object($newAnno) || !method_exists($newAnno, 'getValue')) {
                return null;
            }

            $data = array_merge(
                $data,
                self::buildRequestMapping($anno, $newAnno->getValue()),
                compact('httpMethod')
            );

            return RouteRule::create($data);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param ReflectionMethod $method
     * @param mixed $anno
     * @param array $data
     * @return RouteRule[]
     */
    private static function buildRouteRulesForRequestMapping(
        ReflectionMethod $method,
        mixed $anno,
        array $data
    ): array
    {
        try {
            $newAnno =  ReflectUtils::getMethodAnnotation($method, RequestMapping::class);

            if (!is_object($newAnno) || !method_exists($newAnno, 'getValue')) {
                return [];
            }

            $map1 = self::buildRequestMapping($anno, $newAnno->getValue());

            return [
                RouteRule::create(array_merge($data, $map1, ['httpMethod' => 'GET'])),
                RouteRule::create(array_merge($data, $map1, ['httpMethod' => 'POST']))
            ];
        } catch (Throwable) {
            return [];
        }
    }

    private static function buildRequestMapping(mixed $anno, string $requestMapping): array
    {
        $requestMapping = preg_replace('/[\x20\t]+/', '', $requestMapping);
        $requestMapping = trim($requestMapping, '/');

        if ($anno instanceof RequestMapping) {
            $s1 = preg_replace('/[\x20\t]+/', '', $anno->getValue());

            if (!empty($s1)) {
                $requestMapping = trim($s1, '/') . '/' . $requestMapping;
            }
        }

        $requestMapping = StringUtils::ensureLeft($requestMapping, '/');
        return compact('requestMapping');
    }

    /**
     * @param ReflectionMethod $method
     * @return HandlerFuncArgInfo[]
     */
    private static function buildHandlerFuncArgs(ReflectionMethod $method): array
    {
        $params = $method->getParameters();

        foreach ($params as $i => $p) {
            $type = $p->getType();

            if (!($type instanceof ReflectionNamedType)) {
                $params[$i] = HandlerFuncArgInfo::create(['name' => $p->getName()]);
                continue;
            }

            if ($type->isBuiltin()) {
                $typeName = $type->getName();
            } else {
                $typeName = StringUtils::ensureLeft($type->getName(), "\\");
            }

            $map1 = [
                'name' => $p->getName(),
                'type' => $typeName
            ];

            if ($type->allowsNull()) {
                $map1['nullable'] = true;
            }



            if (str_contains($typeName, Request::class)) {
                $map1['request'] = true;
                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            if (str_contains($typeName, Token::class)) {
                $map1['jwt'] = true;
                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $anno =  ReflectUtils::getParameterAnnotation($p, ClientIp::class);

            if ($anno instanceof ClientIp) {
                $map1['clientIp'] = true;
                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $anno =  ReflectUtils::getParameterAnnotation($p, HttpHeader::class);

            if ($anno instanceof HttpHeader) {
                $map1['httpHeaderName'] = $anno->getName();
                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $anno =  ReflectUtils::getParameterAnnotation($p, JwtClaim::class);

            if ($anno instanceof JwtClaim) {
                $map1['jwtClaimName'] = empty($anno->getName()) ? $p->getName() : $anno->getName();
                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $anno =  ReflectUtils::getParameterAnnotation($p, PathVariable::class);

            if ($anno instanceof PathVariable) {
                $map1['pathVariableName'] = empty($anno->getName()) ? $p->getName() : $anno->getName();
                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $anno =  ReflectUtils::getParameterAnnotation($p, RequestParam::class);

            if ($anno instanceof RequestParam) {
                $map1['requestParamInfo'] = [
                    'name' => empty($anno->getName()) ? $p->getName() : $anno->getName(),
                    'decimal' => $anno->isDecimal(),
                    'securityMode' => $anno->getSecurityMode()
                ];

                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $anno =  ReflectUtils::getParameterAnnotation($p, MapBind::class);

            if ($anno instanceof MapBind) {
                $map1['mapBindSettings'] = [
                    'rules' => $anno->getRules()
                ];

                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $anno =  ReflectUtils::getParameterAnnotation($p, UploadedFile::class);

            if ($anno instanceof UploadedFile) {
                $map1['uploadedFileInfo'] = [
                    'formFieldName' => empty($anno->getValue()) ? $p->getName() : $anno->getValue()
                ];

                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $anno =  ReflectUtils::getParameterAnnotation($p, RequestBody::class);

            if ($anno instanceof RequestBody) {
                $map1['needRequestBody'] = true;
                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $anno = ReflectUtils::getParameterAnnotation($p, DtoBind::class);

            if ($anno instanceof DtoBind && !$type->isBuiltin()) {
                $map1 = array_merge($map1, self::buildDtoBindSettings($p));
                $params[$i] = HandlerFuncArgInfo::create($map1);
                continue;
            }

            $params[$i] = HandlerFuncArgInfo::create($map1);
        }

        return $params;
    }

    private static function buildDtoBindSettings(ReflectionParameter $p): array
    {
        $typeName = StringUtils::ensureLeft($p->getType()->getName(), "\\");

        try {
            $clazz = new ReflectionClass($typeName);
        } catch (Throwable) {
            $clazz = null;
        }

        if (!($clazz instanceof ReflectionClass)) {
            return [];
        }

        $map1 = ['dtoClassName' => StringUtils::ensureLeft($p->getType()->getName(), "\\")];

        foreach ($clazz->getProperties() as $field) {
            $fieldName = $field->getName();
            $anno = ReflectUtils::getPropertyAnnotation($field, MapKey::class);

            if ($anno instanceof MapKey) {
                $map1[$fieldName] = ['mapKey' => $anno->getValue()];
            }

            $anno = ReflectUtils::getPropertyAnnotation($field, BindingDefault::class);

            if ($anno instanceof BindingDefault) {
                if (is_array($map1[$fieldName])) {
                    $map1[$fieldName]['defaultValue'] = $anno->getValue();
                } else {
                    $map1[$fieldName] = ['defaultValue' => $anno->getValue()];
                }
            }

            $anno = ReflectUtils::getPropertyAnnotation($field, ClientIp::class);

            if ($anno instanceof ClientIp) {
                if (is_array($map1[$fieldName])) {
                    $map1[$fieldName]['clientIp'] = true;
                } else {
                    $map1[$fieldName] = ['clientIp' => true];
                }

                continue;
            }

            $anno = ReflectUtils::getPropertyAnnotation($field, HttpHeader::class);

            if ($anno instanceof HttpHeader) {
                if (is_array($map1[$fieldName])) {
                    $map1[$fieldName]['httpHeaderName'] = $anno->getName();
                } else {
                    $map1[$fieldName] =  ['httpHeaderName' => $anno->getName()];
                }

                continue;
            }

            $anno = ReflectUtils::getPropertyAnnotation($field, JwtClaim::class);

            if ($anno instanceof JwtClaim) {
                if (is_array($map1[$fieldName])) {
                    $map1[$fieldName]['jwtClaimName'] = $anno->getName();
                } else {
                    $map1[$fieldName] =  ['jwtClaimName' => $anno->getName()];
                }

                continue;
            }

            $anno = ReflectUtils::getPropertyAnnotation($field, RequestBody::class);

            if ($anno instanceof RequestBody) {
                if (is_array($map1[$fieldName])) {
                    $map1[$fieldName]['needRequestBody'] = true;
                } else {
                    $map1[$fieldName] =  ['needRequestBody' => true];
                }

                continue;
            }

            $anno = ReflectUtils::getPropertyAnnotation($field, UploadedFile::class);

            if ($anno instanceof UploadedFile) {
                if (is_array($map1[$fieldName])) {
                    $map1[$fieldName]['formFieldName'] = $anno->getValue();
                } else {
                    $map1[$fieldName] =  ['formFieldName' => $anno->getValue()];
                }
            }
        }

        return ['dtoBindSettings' => $map1];
    }

    private static function buildJwtAuthSettings(ReflectionMethod $method): array
    {
        $anno =  ReflectUtils::getMethodAnnotation($method, JwtAuth::class);

        if (!($anno instanceof JwtAuth)) {
            return [];
        }

        return ['jwtSettingsKey' => $anno->getValue()];
    }

    private static function buildValidateRules(ReflectionMethod $method): array
    {
        $anno = ReflectUtils::getMethodAnnotation($method, Validate::class);

        if (!($anno instanceof Validate)) {
            return [];
        }

        return ['validateRules' => $anno->getRules(), 'failfast' => $anno->isFailfast()];
    }

    private static function buildExtraAnnotations(ReflectionMethod $method): array
    {
        try {
            $annotations = $method->getAttributes();
        } catch (Throwable) {
            return [];
        }

        $excludes = [
            DeleteMapping::class,
            GetMapping::class,
            JwtAuth::class,
            PatchMapping::class,
            PostMapping::class,
            PutMapping::class,
            RequestMapping::class,
            Validate::class
        ];

        $extraAnnotations = [];

        foreach ($annotations as $anno) {
            $clazz = StringUtils::ensureLeft($anno->getName(), "\\");
            $isExclude = false;

            foreach ($excludes as $s1) {
                if (str_contains($clazz, $s1)) {
                    $isExclude = true;
                    break;
                }
            }

            if ($isExclude) {
                continue;
            }

            if ($anno instanceof Stringable || method_exists($anno, '__toString')) {
                $contents = Cast::toString($anno->__toString());

                if (!str_contains($contents, $clazz)) {
                    $contents = $contents === '' ? $clazz : "$clazz@$contents";
                }
            } else {
                $contents = $clazz;
            }

            $extraAnnotations[] = $contents;
        }

        return compact('extraAnnotations');
    }
}

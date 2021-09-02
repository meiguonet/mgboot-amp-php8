<?php

namespace mgboot\annotation;

use Attribute;
use mgboot\constant\Regexp;
use mgboot\util\ArrayUtils;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class MapBind
{
    /**
     * @var string[]
     */
    private array $rules;

    public function __construct(string|array $arg0 = [])
    {
        $rules = [];

        if (is_string($arg0) && $arg0 !== '') {
            $rules = preg_split(Regexp::COMMA_SEP, $arg0);
        } else if (is_array($arg0) && !empty($arg0)) {
            foreach ($arg0 as $s1) {
                if (is_string($s1) && $s1 !== '') {
                    $rules[] = $s1;
                }
            }
        }

        $this->rules = $rules;
    }

    /**
     * @return string[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}

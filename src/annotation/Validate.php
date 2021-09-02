<?php

namespace mgboot\annotation;

use Attribute;
use mgboot\constant\Regexp;
use mgboot\util\ArrayUtils;

#[Attribute(Attribute::TARGET_METHOD)]
final class Validate
{
    /**
     * @var string[]
     */
    private array $rules;

    private bool $failfast;

    public function __construct(string|array $rules, bool $failfast = false)
    {
        $_rules = [];

        if (is_string($rules) && $rules !== '') {
            $_rules = preg_split(Regexp::COMMA_SEP, $rules);
        } else if (is_array($rules) && !empty($rules)) {
            foreach ($rules as $s1) {
                if (is_string($s1) && $s1 !== '') {
                    $_rules[] = $s1;
                }
            }
        }

        $this->rules = $_rules;
        $this->failfast = $failfast;
    }

    /**
     * @return string[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @return bool
     */
    public function isFailfast(): bool
    {
        return $this->failfast;
    }
}

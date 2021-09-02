<?php

namespace mgboot\annotation;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class GetMapping
{
    private string $value;

    public function __construct(string $arg0)
    {
        $this->value = $arg0;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }
}

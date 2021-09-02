<?php

namespace mgboot\annotation;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Scheduled
{
    private string $value;

    public function __construct(string $arg0)
    {
        $this->value = $arg0;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}

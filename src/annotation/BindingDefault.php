<?php

namespace mgboot\annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class BindingDefault
{
    private int|float|string $value;

    public function __construct(int|float|string $arg0)
    {
        $this->value = $arg0;
    }

    /**
     * @return float|int|string
     */
    public function getValue(): float|int|string
    {
        return $this->value;
    }
}

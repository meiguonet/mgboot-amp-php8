<?php

namespace mgboot\annotation;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class WsTimer
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}

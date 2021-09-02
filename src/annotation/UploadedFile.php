<?php

namespace mgboot\annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class UploadedFile
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

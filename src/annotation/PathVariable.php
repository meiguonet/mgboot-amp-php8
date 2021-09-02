<?php

namespace mgboot\annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class PathVariable
{
    private string $name;

    public function __construct(string $arg0 = '')
    {
        $this->name = $arg0;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}

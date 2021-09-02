<?php

namespace mgboot\databasex;

final class Expression
{
    private string $expr;

    private function __construct(string $expr)
    {
        $this->expr = $expr;
    }

    private function __clone(): void
    {
    }

    public static function create(string $expr): self
    {
        return new self($expr);
    }

    public function getExpr(): string
    {
        return $this->expr;
    }
}

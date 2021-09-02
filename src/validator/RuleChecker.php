<?php

namespace mgboot\validator;

interface RuleChecker
{
    public function getRuleName(): string;

    public function check(string $value, string $checkValue = ''): bool;
}

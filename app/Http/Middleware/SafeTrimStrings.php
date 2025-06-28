<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

class SafeTrimStrings extends Middleware
{
    protected function transform($key, $value)
    {
        return is_string($value) ? trim($value) : $value;
    }
}

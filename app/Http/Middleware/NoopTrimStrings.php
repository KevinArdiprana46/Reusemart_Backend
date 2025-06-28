<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

class NoopTrimStrings extends Middleware
{
    protected function transform($key, $value)
    {
        return $value;
    }
}

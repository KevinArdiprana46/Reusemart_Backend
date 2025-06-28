<?php

namespace App\Traits;

trait SafeToArray
{
    public function toArray()
    {
        return $this->only(array_keys($this->getAttributes()));
    }
}

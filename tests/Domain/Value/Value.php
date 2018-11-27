<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Value;

use RuntimeException;

abstract class Value
{
    protected $__immutable__ = false;

    public function __construct()
    {
        if ($this->__immutable__) {
            throw new RuntimeException('immutable');
        }
        $this->__immutable__ = true;
    }

    public function __get($key)
    {
        throw new RuntimeException('immutable');
    }

    public function __set($key, $val)
    {
        throw new RuntimeException('immutable');
    }

    public function __isset($key)
    {
        throw new RuntimeException('immutable');
    }

    public function __unset($key)
    {
        throw new RuntimeException('immutable');
    }

    protected function with(array $props)
    {
        $clone = clone $this;
        foreach ($props as $name => $value) {
            $clone->$name = $value;
        }
        return $clone;
    }

    public function getArrayCopy()
    {
        $copy = get_object_vars($this);
        unset($copy['__immutable__']);
        return $copy;
    }
}

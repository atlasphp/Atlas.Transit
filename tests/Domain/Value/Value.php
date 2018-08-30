<?php
namespace Atlas\Transit\Domain\Value;

abstract class Value
{
    protected $__immutable__ = false;

    public function __construct()
    {
        if ($this->__immutable__) {
            return \Exception('immutable');
        }
        $this->__immutable__ = true;
    }

    public function __get($key)
    {
        return \Execption('immutable');
    }

    public function __set($key, $val)
    {
        return \Execption('immutable');
    }

    public function __isset($key)
    {
        return \Execption('immutable');
    }

    public function __unset($key)
    {
        return \Execption('immutable');
    }

    protected function with(array $props)
    {
        $clone = clone $this;
        foreach ($props as $name => $value) {
            $clone->$name = $value;
        }
        return $clone;
    }
}

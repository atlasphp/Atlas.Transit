<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Value;

use RuntimeException;

abstract class Value
{
    private $__INITIALIZED__ = false;

    public function __construct()
    {
        if ($this->__INITIALIZED__) {
            throw $this->immutableException();
        }
        $this->__INITIALIZED__ = true;
    }

    public function __get(string $key)
    {
        return $this->$key;
    }

    public function __set(string $key, $val) : void
    {
        throw $this->immutableException();
    }

    public function __isset(string $key)
    {
        return isset($this->$key);
    }

    public function __unset(string $key) : void
    {
        throw $this->immutableException();
    }

    public function __debugInfo() : array
    {
        $info = get_object_vars($this);
        unset($info['__INITIALIZED__']);
        return $info;
    }

    public function getArrayCopy()
    {
        $copy = get_object_vars($this);
        unset($copy['__INITIALIZED__']);
        return $copy;
    }

    protected function with(array $properties) // : static
    {
        unset($properties['__INITIALIZED__']);

        if ($this->__INITIALIZED__) {
            $object = clone $this;
        } else {
            $object = $this;
        }

        foreach ($properties as $name => $value) {
            if (! property_exists($this, $name)) {
                throw $this->immutableException();
            }
            $object->$name = $value;
        }

        return $object;
    }

    private function immutableException() : RuntimeException
    {
        $message = get_class($this) . ' cannot be modified after construction.';
        return new RuntimeException($message);
    }
}

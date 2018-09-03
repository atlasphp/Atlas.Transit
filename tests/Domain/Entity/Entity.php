<?php
namespace Atlas\Transit\Domain\Entity;

use Atlas\Transit\Domain\Value\Value;

abstract class Entity
{
    public function __get($key)
    {
        return $this->$key;
    }

    public function getArrayCopy()
    {
        $copy = [];
        foreach (get_object_vars($this) as $key => $val) {
            if (is_callable([$val, 'getArrayCopy'])) {
                $copy[$key] = $val->getArrayCopy();
            } else {
                $copy[$key] = $val;
            }
        }
        return $copy;
    }
}

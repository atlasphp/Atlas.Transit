<?php
namespace Atlas\Transit\Domain\Entity;

use DateTimeImmutable;

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
            if ($val instanceof Entity || $val instanceof EntityCollection) {
                $copy[$key] = $val->getArrayCopy();
            } else {
                $copy[$key] = $val;
            }
        }
        return $copy;
    }
}

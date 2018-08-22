<?php
namespace Atlas\Transit\Domain\Entity;

use DateTimeImmutable;

abstract class Entity
{
    public function getArrayCopy()
    {
        $copy = [];
        foreach (get_object_vars($this) as $key => $val) {
            if ($val instanceof Entity || $val instanceof EntityCollection) {
                $copy[$key] = $val->getArrayCopy();
            } elseif ($val instanceof DateTimeImmutable) {
                $copy[$key] = $val->format('Y-m-d H:i:s T');
            } else {
                $copy[$key] = $val;
            }
        }
        return $copy;
    }
}

<?php
namespace Atlas\Transit\Domain;

use DateTimeImmutable;

abstract class DomainObject
{
    public function getArrayCopy()
    {
        $copy = [];
        foreach (get_object_vars($this) as $key => $val) {
            if ($val instanceof DomainObject) {
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

<?php
declare(strict_types=1);

namespace Atlas\Transit\Casing;

abstract class Casing
{
    abstract public function explode(string $name) : array;
    abstract public function implode(array $parts) : string;
}

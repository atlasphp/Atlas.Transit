<?php
namespace Atlas\Transit\CaseConverter;

abstract class CaseConverter
{
    public function convert(string $name, CaseConverter $target) : string
    {
        return $target->implode($this->explode($name));
    }

    abstract public function explode(string $name) : array;

    abstract public function implode(array $parts) : string;
}

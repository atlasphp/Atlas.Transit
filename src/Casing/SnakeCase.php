<?php
namespace Atlas\Transit\Casing;

class SnakeCase extends Casing
{
    public function explode(string $name) : array
    {
        return explode('_', $name);
    }

    public function implode(array $parts) : string
    {
        foreach ($parts as &$part) {
            $part = strtolower($part);
        }
        return implode('_', $parts);
    }
}

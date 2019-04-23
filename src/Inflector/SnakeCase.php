<?php
declare(strict_types=1);

namespace Atlas\Transit\Inflector;

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

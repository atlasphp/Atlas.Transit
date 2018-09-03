<?php
declare(strict_types=1);

namespace Atlas\Transit\Casing;

class PascalCase extends Casing
{
    public function explode(string $name) : array
    {
        return preg_split('/(?=[A-Z])/', $name, -1, PREG_SPLIT_NO_EMPTY);
    }

    public function implode(array $parts) : string
    {
        $name = '';
        foreach ($parts as $part) {
            $name .= ucfirst($part);
        }
        return $name;
    }
}

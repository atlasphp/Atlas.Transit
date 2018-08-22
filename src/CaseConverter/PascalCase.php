<?php
namespace Atlas\Transit\CaseConverter;

class PascalCase extends ACase
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

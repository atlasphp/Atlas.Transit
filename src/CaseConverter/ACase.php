<?php
namespace Atlas\Transit\CaseConverter;

/**
 * The word `Case` causes a syntax error, so `ACase` it is.
 */
abstract class ACase
{
    abstract public function explode(string $name) : array;
    abstract public function implode(array $parts) : string;
}

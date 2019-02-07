<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

use ReflectionClass;

abstract class Reflection
{
    protected $type;

    protected $domainClass;

    protected $docComment;

    public function __construct(
        ReflectionClass $r,
        ReflectionLocator $reflectionLocator
    ) {
        $this->domainClass = $r->getName();
        $this->docComment = $r->getDocComment();
    }

    public function __get(string $key)
    {
        return $this->$key;
    }
}

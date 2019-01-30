<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

use ReflectionClass;

abstract class Reflection
{
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

    protected function getAnnotatedMaperClass() : ?string
    {
        $found = preg_match(
            '/^\s*\*\s*@Atlas\\\\Transit\\\\' . $this->type . '[ \t]+(.*)/m',
            $this->docComment,
            $matches
        );

        if ($found === 1) {
            return ltrim(trim($matches[1]), '\\');
        }

        return null;
    }
}

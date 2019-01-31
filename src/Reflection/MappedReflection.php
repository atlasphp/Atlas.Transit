<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

abstract class MappedReflection extends Reflection
{
    protected $mapperClass;

    abstract protected function setMapperClass(ReflectionLocator $reflectionLocator) : void;

    protected function getAnnotatedMaperClass() : ?string
    {
        $found = preg_match(
            '/^\s*\*\s*@Atlas\\\\Transit\\\\Mapper[ \t]+(.*)/m',
            $this->docComment,
            $matches
        );

        if ($found === 1) {
            return ltrim(trim($matches[1]), '\\');
        }

        return null;
    }
}

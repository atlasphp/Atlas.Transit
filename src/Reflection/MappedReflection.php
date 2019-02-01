<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

abstract class MappedReflection extends Reflection
{
    protected $mapperClass;

    protected $sourceMethod;

    abstract protected function setMapperClass(ReflectionLocator $reflectionLocator) : void;

    abstract protected function setSourceMethod() : void;

    protected function getAnnotatedMaperClass() : ?string
    {
        $found = preg_match(
            '/^\s*\*\s*@Atlas\\\\Transit\\\\Mapper[ \t]+(.*)\s/m',
            $this->docComment,
            $matches
        );

        if (empty($matches)) {
            return null;
        }

        return ltrim(trim($matches[1]), '\\');
    }

    protected function getAnnotatedSourceMethod() : ?string
    {
        $found = preg_match(
            '/^\s*\*\s*@Atlas\\\\Transit\\\\Source[ \t]+(.*)\s/m',
            $this->docComment,
            $matches
        );

        if (empty($matches)) {
            return null;
        }

        $sourceMethod = trim($matches[1]);
        if (substr($sourceMethod, -2) == '()') {
            $sourceMethod = substr($sourceMethod, 0, -2);
        }

        return $sourceMethod;
    }
}

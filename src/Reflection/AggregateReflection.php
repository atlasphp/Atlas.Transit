<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

use Atlas\Transit\Inflector\Inflector;
use ReflectionClass;

class AggregateReflection extends ParameterReflection
{
    protected $type = 'Aggregate';
    protected $rootClass;

    public function __construct(
        ReflectionClass $r,
        ReflectionLocator $reflectionLocator
    ) {
        parent::__construct($r, $reflectionLocator);

        $found = preg_match(
            '/^\s*\*\s*@Atlas\\\\Transit\\\\AggregateRoot[ \t]+\$?(.*)/m',
            $this->docComment,
            $matches
        );

        if ($found === 1) {
            $name = trim($matches[1]);
            // @todo blow up if no matching param name
            $rootParam = $this->parameters[$name];
        } else {
            $rootParam = reset($this->parameters);
        }

        $this->rootClass = $rootParam->getClass()->getName();

        $this->setMapperClass($reflectionLocator);
    }

    protected function setMapperClass(ReflectionLocator $reflectionLocator) : void
    {
        $this->mapperClass = $reflectionLocator->get($this->rootClass)->mapperClass;
    }
}

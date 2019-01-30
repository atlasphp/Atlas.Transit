<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

use Atlas\Transit\Inflector\Inflector;
use ReflectionClass;

abstract class ParameterReflection extends Reflection
{
    protected $parameters = [];
    protected $properties = [];
    protected $mapperClass;
    protected $fromDomainToSource = [];
    protected $types = [];
    protected $classes = [];
    protected $autoincColumn;

    public function __construct(
        ReflectionClass $r,
        ReflectionLocator $reflectionLocator
    ) {
        parent::__construct($r, $reflectionLocator);
        $this->setParameters($r, $reflectionLocator->getInflector());
    }

    protected function setParameters(ReflectionClass $r, Inflector $inflector)
    {
        $this->parameters = [];
        $this->properties = [];
        $this->fromDomainToSource = [];

        $rparams = $r->getMethod('__construct')->getParameters();

        foreach ($rparams as $rparam) {
            $name = $rparam->getName();
            $this->parameters[$name] = $rparam;

            $found = preg_match(
                '/^\s*\*\s*@Atlas\\\\Transit\\\\Parameter[ \t]+\$?' . $name . '[ \t]+\$?(.*)/m',
                $this->docComment,
                $matches
            );
            if ($found === 1) {
                $field = $matches[1];
            } else {
                $field = $inflector->fromDomainToSource($name);
            }
            $this->fromDomainToSource[$name] = $field;

            if ($r->hasProperty($name)) {
                $rprop = $r->getProperty($name);
                $rprop->setAccessible(true);
                $this->properties[$name] = $rprop;
            }

            $this->types[$name] = null;
            $this->classes[$name] = null;

            $class = $rparam->getClass();
            if ($class !== null) {
                $this->classes[$name] = $class->getName();
                continue;
            }

            $type = $rparam->getType();
            if ($type === null) {
                continue;
            }

            $this->types[$name] = $type->getName();
        }
    }
}

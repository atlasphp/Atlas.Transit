<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

use ReflectionClass;

trait ParametersTrait
{
    protected $parameterCount = 0;

    protected $parameters = [];

    protected $properties = [];

    protected $fromDomainToSource = [];

    protected $types = [];

    protected $classes = [];

    protected function setParameters(
        ReflectionClass $r,
        ReflectionLocator $reflectionLocator
    ) {
        $inflector = $reflectionLocator->getInflector();

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

            $type = $rparam->getType();
            $class = null !== $type && !$type->isBuiltin() ? $type->getName() : null;

            if ($class !== null) {
                $this->classes[$name] = $class;
                continue;
            }

            if ($type === null) {
                continue;
            }

            $this->types[$name] = $type->getName();
        }

        $this->parameterCount = count($this->parameters);
    }

    public function getFirstParameter()
    {
        return reset($this->parameters);
    }

    public function getFirstProperty()
    {
        return reset($this->properties);
    }
}

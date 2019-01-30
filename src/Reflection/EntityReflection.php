<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

use Atlas\Transit\Inflector\Inflector;
use ReflectionClass;

class EntityReflection extends Reflection
{
    public $type = 'Entity';
    public $parameters = [];
    public $properties = [];
    public $mapperClass;
    public $fromDomainToSource = [];
    public $types = [];
    public $classes = [];

    public function __construct(ReflectionClass $r, string $docComment, string $sourceNamespace, Inflector $inflector)
    {
        parent::__construct($r, $docComment, $sourceNamespace, $inflector);
        $this->setParameters($r);

        $found = preg_match(
            '/^\s*\*\s*@Atlas\\\\Transit\\\\' . $this->type . '[ \t]+(.*)/m',
            $this->docComment,
            $matches
        );

        if ($found === 1) {
            // explicit by annotation
            $this->mapperClass = ltrim(trim($matches[1]), '\\');
            return;
        }

        // implicit by domain class
        $final = strrchr($this->domainClass, '\\');
        if (
            $this->type === 'Collection'
            && substr($final, -10) === 'Collection'
        ) {
            $final = substr($final, 0, -10);
        }

        $this->mapperClass = $sourceNamespace . $final . $final;
    }

    protected function setParameters(ReflectionClass $r)
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
                $field = $this->inflector->fromDomainToSource($name);
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

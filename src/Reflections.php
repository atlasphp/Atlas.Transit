<?php
declare(strict_types=1);

namespace Atlas\Transit;

use ReflectionClass;

class Reflections
{
    protected $bag = [];

    protected $sourceNamespace;

    protected $inflector;

    public function __construct(
        string $sourceNamespace,
        Inflector $inflector
    ) {
        $this->sourceNamespace = rtrim($sourceNamespace, '\\');
        $this->inflector = $inflector;
    }

    public function get(string $domainClass) : object
    {
        if (! isset($this->bag[$domainClass])) {
            $this->set($domainClass);
        }

        return $this->bag[$domainClass]->transit;
    }

    protected function set(string $domainClass) : void
    {
        $r = new ReflectionClass($domainClass);
        $r->transit = (object) [
            'domainClass' => $domainClass,
            'type' => null,
        ];

        $this->bag[$domainClass] = $r;

        $r->transit->docComment = $r->getDocComment();

        if ($r->transit->docComment === false) {
            return;
        }

        $found = preg_match(
            '/^\s*\*\s*@Atlas\\\\Transit\\\\(Entity|Aggregate|Collection)\b/m',
            $r->transit->docComment,
            $matches
        );

        if ($found !== 1) {
            return;
        }

        $r->transit->type = trim($matches[1]);

        $method = 'set' . $r->transit->type;
        $this->$method($r);
    }

    protected function setEntity(ReflectionClass $r) : void
    {
        $this->setMapperClass($r);
        $this->setParameters($r);
    }

    protected function setCollection(ReflectionClass $r) : void
    {
        $this->setMapperClass($r);
    }

    protected function setAggregate(ReflectionClass $r) : void
    {
        $this->setParameters($r);

        $r->transit->rootClass = reset($r->transit->parameters)->getClass()->getName();
        $rootEntity = $this->get($r->transit->rootClass);

        $r->transit->mapperClass = $rootEntity->mapperClass;
    }

    protected function setValueObject(ReflectionClass $r) : void
    {

    }

    protected function setMapperClass(ReflectionClass $r) : void
    {
        $found = preg_match(
            '/^\s*\*\s*@Atlas\\\\Transit\\\\' . $r->transit->type . '\\\\Mapper\s+(.*)/m',
            $r->transit->docComment,
            $matches
        );

        if ($found === 1) {
            // explicit by annotation
            $r->transit->mapperClass = ltrim(trim($matches[1]), '\\');
            return;
        }

        // implicit by domain class
        $final = strrchr($r->transit->domainClass, '\\');
        if (
            $r->transit->type === 'Collection'
            && substr($final, -10) === 'Collection'
        ) {
            $final = substr($final, 0, -10);
        }

        $r->transit->mapperClass = $this->sourceNamespace . $final . $final;
    }

    protected function setParameters(ReflectionClass $r)
    {
        $r->transit->parameters = [];
        $r->transit->properties = [];
        $r->transit->fromDomainToSource = [];

        $rparams = $r->getMethod('__construct')->getParameters();

        foreach ($rparams as $rparam) {
            $name = $rparam->getName();
            $r->transit->parameters[$name] = $rparam;

            $found = preg_match(
                '/^\s*\*\s*@Atlas\\\\Transit\\\\' . $r->transit->type . '\\\\Parameter\s+\$?' . $name . '\s+\$?(.*)/m',
                $r->transit->docComment,
                $matches
            );
            if ($found === 1) {
                $field = $matches[1];
            } else {
                $field = $this->inflector->fromDomainToSource($name);
            }
            $r->transit->fromDomainToSource[$name] = $field;

            if ($r->hasProperty($name)) {
                $rprop = $r->getProperty($name);
                $rprop->setAccessible(true);
                $r->transit->properties[$name] = $rprop;
            }

            $r->transit->types[$name] = null;
            $r->transit->classes[$name] = null;

            $class = $rparam->getClass();
            if ($class !== null) {
                $r->transit->classes[$name] = $class->getName();
                continue;
            }

            $type = $rparam->getType();
            if ($type === null) {
                continue;
            }

            $r->transit->types[$name] = $type->getName();
        }
    }
}

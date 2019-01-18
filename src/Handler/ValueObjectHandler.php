<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Record;
use Atlas\Transit\CaseConverter;
use Atlas\Transit\Exception;
use ReflectionClass;
use ReflectionParameter;

class ValueObjectHandler
{
    protected $reflectionClasses = [];
    protected $transitFromSource = [];
    protected $transitIntoSource = [];
    protected $constructorParamCount = [];
    protected $properties = [];

    public function __construct(CaseConverter $caseConverter)
    {
        $this->caseConverter = $caseConverter;
    }

    public function newDomainArgument(
        string $class, // VO class
        Record $record,
        string $field
    ) : object
    {
        $rclass = $this->getReflectionClass($class);

        /* custom factory */
        if (isset($this->transitFromSource[$class])) {
            return $this->transitFromSource[$class]->invoke(null, $record, $field);
        }

        /* no constructor, or no constructor params */
        if ($this->constructorParamCount[$class] == 0) {
            return new $class();
        }

        /* single scalar constructor param with matching name */
        if (
            $this->constructorParamCount[$class] == 1
            && $record->has($field)
        ) {
            return new $class($record->$field);
        }

        /* multiple scalar constructor params, or no matching name */

        // look for fields with the domain property prefix;
        // e.g., address_street, address_city, address_state, address_zip
        $args = [];
        foreach ($this->constructorParams[$class] as $name => $type) {
            $fixed = $this->caseConverter->fromDomainToSource("{$field}_{$name}");
            if (! $record->has($fixed)) {
                break;
            }
            $arg = $record->$fixed;
            if ($type !== null) {
                settype($arg, $type);
            }
            $args[] = $arg;
        }

        if (count($args) === count($this->constructorParams[$class])) {
            return new $class(...$args);
        }

        // look for fields without the domain property prefix;
        // e.g., street, city, state, zip
        $args = [];
        foreach ($this->constructorParams[$class] as $name => $type) {
            $fixed = $this->caseConverter->fromDomainToSource($name);
            if (! $record->has($fixed)) {
                break;
            }
            $arg = $record->$fixed;
            if ($type !== null) {
                settype($arg, $type);
            }
            $args[] = $arg;
        }

        if (count($args) === count($this->constructorParams[$class])) {
            return new $class(...$args);
        }

        // cannot continue
        throw new Exception("Cannot auto-create {$name} value object of {$class}.");
    }

    public function updateSourceFieldObject(
        Record $record,
        string $field,
        object $datum
    ) : void
    {
        $class = get_class($datum);
        $rclass = $this->getReflectionClass($class);

        /* custom updater */
        if (isset($this->transitIntoSource[$class])) {
            $this->transitIntoSource[$class]->invoke($datum, $record, $field);
            return;
        }

        /* no constructor params, or no constructor */
        if ($this->constructorParamCount[$class] === 0) {
            return;
        }

        /* one constructor param of matching name */
        if (
            $this->constructorParamCount[$class] === 1
            && $record->has($field)
        ) {
            $rprop = reset($this->properties[$class]);
            $record->$field = $rprop->getValue($datum);
            return;
        }

        /* one or more scalar constructor params, or no matching name */

        // look for fields with the domain property prefix;
        // e.g., address_street, address_city, address_state, address_zip
        $args = [];
        foreach ($this->constructorParams[$class] as $name => $type) {
            $rprop = $this->properties[$class][$name];
            $fixed = $this->caseConverter->fromDomainToSource("{$field}_{$name}");
            if (! $record->has($fixed)) {
                break;
            }
            $args[$fixed] = $rprop->getValue($datum);
        }

        if (count($args) === $this->constructorParamCount[$class]) {
            foreach ($args as $key => $val) {
                $record->$key = $val;
            }
            return;
        }

        // look for fields without the domain property prefix;
        // e.g., street, city, state, zip
        $args = [];
        foreach ($this->constructorParams[$class] as $name => $type) {
            $rprop = $this->properties[$class][$name];
            $fixed = $this->caseConverter->fromDomainToSource($name);
            if (! $record->has($fixed)) {
                break;
            }
            $args[$fixed] = $rprop->getValue($datum);
        }

        if (count($args) === $this->constructorParamCount[$class]) {
            foreach ($args as $key => $val) {
                $record->$key = $val;
            }
            return;
        }

        // cannot continue
        throw new Exception("Cannot extract {$name} value from domain object {$class}; does not have a property matching the constructor parameter.");
    }

    // rename to loadRefectionClass()
    protected function getReflectionClass(string $class)
    {
        if (! isset($this->reflectionClasses[$class])) {
            $this->newReflection($class);
        }

        return $this->reflectionClasses[$class];
    }

    protected function newReflection(string $class)
    {
        $rclass = new ReflectionClass($class);
        $this->reflectionClasses[$class] = $rclass;

        $this->transitFromSource[$class] = null;
        if ($rclass->hasMethod('__transitFromSource')) {
            $rmethod = $rclass->getMethod('__transitFromSource');
            $rmethod->setAccessible(true);
            $this->transitFromSource[$class] = $rmethod;
        }

        $this->transitIntoSource[$class] = null;
        if ($rclass->hasMethod('__transitIntoSource')) {
            $rmethod = $rclass->getMethod('__transitIntoSource');
            $rmethod->setAccessible(true);
            $this->transitIntoSource[$class] = $rmethod;
        }

        $this->constructorParamCount[$class] = 0;
        $rctor = $rclass->getConstructor();
        if ($rctor !== null) {
            $this->constructorParamCount[$class] = $rctor->getNumberOfParameters();
        }

        $this->constructorParams[$class] = [];
        foreach ($rctor->getParameters() as $rparam) {
            $name = $rparam->getName();
            $type = null;
            if ($rparam->hasType()) {
                $type = $rparam->getType()->getName();
            }
            $this->constructorParams[$class][$name] = $type;
        }

        $this->properties[$class] = [];
        foreach ($rclass->getProperties() as $rprop) {
            $rprop->setAccessible(true);
            $this->properties[$class][$rprop->getName()] = $rprop;
        }
    }
}

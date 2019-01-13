<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Record;
use Atlas\Transit\Exception;
use ReflectionClass;
use ReflectionParameter;

class ValueObjectHandler
{
    protected $reflectionClasses = [];
    protected $transitFromSource = [];
    protected $transitIntoSource = [];
    protected $constructorParamCount = [];

    public function newDomainArgument(
        string $class, // VO class
        Record $record,
        string $field
    ) : object
    {
        $rclass = $this->getReflectionClass($class);

        if (isset($this->transitFromSource[$class])) {
            return $this->transitFromSource[$class]->invoke(null, $record, $field);
        }

        if ($this->constructorParamCount == 0) {
            return new $class();
        }

        return new $class($datum);
    }

    public function updateSourceFieldObject(
        Record $record,
        string $field,
        object $datum
    ) : void
    {
        $class = get_class($datum);
        $rclass = $this->getReflectionClass($class);

        if (isset($this->transitIntoSource[$class])) {
            $this->transitIntoSource[$class]->invoke($datum, $record, $field);
            return;
        }

        if (! $record->has($field)) {
            return;
        }

        $rparam = $rclass->getConstructor()->getParameters()[0];
        $name = $rparam->getName();
        $rprops = $rclass->getProperties();
        foreach ($rprops as $rprop) {
            if ($rprop->getName() === $name) {
                $rprop->setAccessible(true);
                $record->$field = $rprop->getValue($datum);
                return;
            }
        }

        throw new Exception("Cannot extract {$name} value from domain object {$class}; does not have a property matching the constructor parameter.");
    }

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
    }
}

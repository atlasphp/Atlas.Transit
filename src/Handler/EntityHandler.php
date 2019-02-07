<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Mapper;
use Atlas\Mapper\Record;
use Atlas\Transit\Inflector;
use Atlas\Transit\Exception;
use Atlas\Transit\Transit;
use Atlas\Transit\Reflection\EntityReflection;
use ReflectionParameter;
use ReflectionProperty;
use SplObjectStorage;

class EntityHandler extends MappedHandler
{
    public function newDomain($record)
    {
        $args = [];
        foreach ($this->reflection->parameters as $name => $param) {
            $args[] = $this->newDomainArgument($param, $record);
        }

        $domainClass = $this->reflection->domainClass;
        $domain = new $domainClass(...$args);
        $this->storage->attach($domain, $record);
        return $domain;
    }

    protected function newDomainArgument(
        ReflectionParameter $param,
        Record $record
    ) {
        $name = $param->getName();

        $field = $this->reflection->fromDomainToSource[$name];
        if ($record->has($field)) {
            $datum = $record->$field;
        } elseif ($param->isDefaultValueAvailable()) {
            $datum = $param->getDefaultValue();
        } else {
            $datum = null;
        }

        if ($param->allowsNull() && $datum === null) {
            return $datum;
        }

        // non-class typehint?
        $type = $this->reflection->types[$name];
        if ($type !== null) {
            settype($datum, $type);
        }

        // class typehint?
        $class = $this->reflection->classes[$name];
        if ($class === null) {
            // note that this returns the non-class typed value as well
            return $datum;
        }

        // when you fetch with() a relationship, but there is no related,
        // Atlas Mapper returns `false`. as such, treat `false` like `null`
        // for class typehints.
        if ($param->allowsNull() && $datum === false) {
            return null;
        }

        // a handled domain class?
        $subhandler = $this->handlerLocator->get($class);
        return $subhandler instanceof ValueObjectHandler
            ? $subhandler->newDomain($record, $field)
            : $subhandler->newDomain($datum);
    }

    public function updateSource(object $domain, SplObjectStorage $refresh)
    {
        if (! $this->storage->contains($domain)) {
            $this->newSource($domain, $refresh);
        }

        $record = $this->storage[$domain];
        return $this->updateSourceFields($domain, $record, $refresh);
    }

    protected function updateSourceFields(object $domain, Record $record, SplObjectStorage $refresh)
    {
        foreach ($this->reflection->properties as $name => $property) {
            $field = $this->reflection->fromDomainToSource[$name];
            $datum = $property->getValue($domain);
            $this->updateSourceField(
                $record,
                $field,
                $datum,
                $refresh
            );
        }

        return $record;
    }

    protected function updateSourceField(
        Record $record,
        string $field,
        $datum,
        SplObjectStorage $refresh
    ) : void
    {
        if (is_object($datum)) {
            $this->updateSourceFieldObject($record, $field, $datum, $refresh);
            return;
        }

        if ($record->has($field)) {
            $record->$field = $datum;
        }
    }

    protected function updateSourceFieldObject(Record $record, string $field, $datum, SplObjectStorage $refresh)
    {
        $handler = $this->handlerLocator->get($datum);
        if ($handler instanceof ValueObjectHandler) {
            $handler->updateSourceFieldObject($record, $field, $datum);
        } else {
            $value = $handler->updateSource($datum, $refresh);
            if ($record->has($field)) {
                $record->$field = $value;
            }
        }
    }

    public function refreshDomain(object $domain, SplObjectStorage $refresh)
    {
        $record = $this->storage[$domain];
        $this->refreshDomainProperties($domain, $record, $refresh);
    }

    public function refreshDomainProperties(object $domain, $record, SplObjectStorage $refresh)
    {
        foreach ($this->reflection->properties as $name => $prop) {
            $this->refreshDomainProperty($prop, $domain, $record, $refresh);
        }

        $refresh->detach($domain);
    }

    protected function refreshDomainProperty(
        ReflectionProperty $prop,
        object $domain,
        $record,
        SplObjectStorage $refresh
    ) : void
    {
        $name = $prop->getName();
        $field = $this->reflection->fromDomainToSource[$name];

        if (
            $this->reflection->type === 'Entity'
            && $this->reflection->autoincColumn === $field
        ) {
            $type = $this->reflection->types[$name];
            $datum = $record->$field;
            if ($type !== null && $datum !== null) {
                settype($datum, $type);
            }
            $prop->setValue($domain, $datum);
            return;
        }

        $datum = $prop->getValue($domain);
        if (! is_object($datum)) {
            return;
        }

        // refresh the underlying domain object
        $this->handlerLocator->get($datum)->refreshDomain($datum, $refresh);
    }
}

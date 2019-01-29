<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Mapper;
use Atlas\Mapper\Record;
use Atlas\Transit\Inflector;
use Atlas\Transit\Exception;
use Atlas\Transit\Transit;
use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;
use SplObjectStorage;

class EntityHandler extends Handler
{
    protected $reflection;
    protected $autoincColumn;
    protected $valueObjectHandler;

    public function __construct(
        string $domainClass,
        object $reflection,
        Mapper $mapper,
        HandlerLocator $handlerLocator,
        SplObjectStorage $storage,
        ValueObjectHandler $valueObjectHandler
    ) {
        parent::__construct($domainClass, $mapper, $handlerLocator, $storage);
        $this->valueObjectHandler = $valueObjectHandler;
        $this->reflection = $reflection;
        $tableClass = get_class($this->mapper) . 'Table';
        $this->autoincColumn = $tableClass::AUTOINC_COLUMN;
    }

    public function newSource(object $domain, SplObjectStorage $refresh) : object
    {
        $source = $this->mapper->newRecord();
        $this->storage->attach($domain, $source);
        $refresh->attach($domain);
        return $source;
    }

    public function getType(string $name)
    {
        return $this->reflection->types[$name];
    }

    public function getClass(string $name)
    {
        return $this->reflection->classes[$name];
    }

    public function newDomain($record)
    {
        $args = [];
        foreach ($this->reflection->parameters as $name => $param) {
            $args[] = $this->newDomainArgument($param, $record);
        }

        $domainClass = $this->domainClass;
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
        $type = $this->getType($name);
        if ($type !== null) {
            settype($datum, $type);
        }

        // class typehint?
        $class = $this->getClass($name);
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
        if ($subhandler !== null) {
            // use subhandler for domain object
            return $subhandler->newDomain($datum);
        }

        // presume a value object
        return $this->valueObjectHandler->newDomainArgument($class, $record, $field);
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
        if ($handler !== null) {
            $value = $handler->updateSource($datum, $refresh);
            if ($record->has($field)) {
                $record->$field = $value;
            }
            return;
        }

        $this->valueObjectHandler->updateSourceFieldObject($record, $field, $datum);
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

        if ($this->autoincColumn === $field) {
            $type = $this->getType($name);
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

        // is the property a type handled by Transit?
        $class = get_class($datum);
        $subhandler = $this->handlerLocator->get($class);
        if ($subhandler !== null) {
            // because there may be domain objects not created through Transit
            $subhandler->refreshDomain($datum, $refresh);
            return;
        }
    }
}

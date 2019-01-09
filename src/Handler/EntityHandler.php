<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Mapper;
use Atlas\Mapper\Record;
use Atlas\Transit\CaseConverter;
use Atlas\Transit\DataConverter;
use Atlas\Transit\Exception;
use Atlas\Transit\Transit;
use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;
use SplObjectStorage;

class EntityHandler extends Handler
{
    protected $caseConverter;
    protected $parameters = [];
    protected $properties = [];
    protected $types = [];
    protected $classes = [];
    protected $dataConverter;
    protected $autoincColumn;

    public function __construct(
        string $domainClass,
        Mapper $mapper,
        HandlerLocator $handlerLocator,
        SplObjectStorage $storage,
        CaseConverter $caseConverter,
        DataConverter $dataConverter
    ) {
        parent::__construct($domainClass, $mapper, $handlerLocator, $storage);
        $this->caseConverter = $caseConverter;
        $this->dataConverter = $dataConverter;

        $rclass = new ReflectionClass($this->domainClass);

        foreach ($rclass->getProperties() as $rprop) {
            $rprop->setAccessible(true);
            $this->properties[$rprop->getName()] = $rprop;
        }

        $rmethod = $rclass->getMethod('__construct');
        foreach ($rmethod->getParameters($rmethod) as $rparam) {
            $name = $rparam->getName();
            $this->parameters[$name] = $rparam;
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

        $tableClass = get_class($this->mapper) . 'Table';
        $this->autoincColumn = $tableClass::AUTOINC_COLUMN;

        /** @todo allow for factories and dependency injection */
        $dataConverter = $this->domainClass . 'DataConverter';
        if (! class_exists($dataConverter)) {
            $dataConverter = DataConverter::CLASS;
        }
        $this->dataConverter = new $dataConverter();
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
        return $this->types[$name];
    }

    public function getClass(string $name)
    {
        return $this->classes[$name];
    }

    public function newDomain($record)
    {
        $args = [];
        foreach ($this->parameters as $name => $param) {
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

        // custom approach
        $method = "__{$name}FromSource";
        if (method_exists($this->dataConverter, $method)) {
            return $this->dataConverter->$method($record);
        }

        // default approach
        $field = $this->caseConverter->fromDomainToSource($name);
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
            return $datum;
        }

        // class typehint?
        $class = $this->getClass($name);
        if ($class === null) {
            return $datum;
        }

        // when you fetch with() a relationship, but there is no related,
        // Atlas Mapper returns `false`. as such, treat `false` like `null`
        // for class typehints.
        if ($param->allowsNull() && $datum === false) {
            return null;
        }

        // any value => a known domain class
        $subhandler = $this->handlerLocator->get($class);
        if ($subhandler !== null) {
            // use subhandler for domain object
            return $subhandler->newDomain($datum);
        }

        // @todo report the domain class and what converter was being used
        throw new Exception("No handler for \$" . $param->getName() . " typehint of {$class}.");
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
        $data = [];
        foreach ($this->properties as $name => $property) {

            // custom approach
            $custom = "__{$name}IntoSource";
            if (method_exists($this->dataConverter, $custom)) {
                $this->dataConverter->$custom($record, $property->getValue($domain));
                continue;
            }

            $datum = $this->updateSourceDatum(
                $domain,
                $record,
                $property->getValue($domain),
                $refresh
            );

            $field = $this->caseConverter->fromDomainToSource($name);
            if ($record->has($field)) {
                $record->$field = $datum;
            }
        }

        return $record;
    }

    // basically, we look to see if the $datum has a handler or not.
    // if it does, we update the $datum as well.
    protected function updateSourceDatum(
        object $domain,
        Record $record,
        $datum,
        SplObjectStorage $refresh
    ) {
        if (! is_object($datum)) {
            return $datum;
        }

        $handler = $this->handlerLocator->get($datum);
        if ($handler !== null) {
            return $handler->updateSource($datum, $refresh);
        }

        return $datum;
    }

    public function refreshDomain(object $domain, SplObjectStorage $refresh)
    {
        $record = $this->storage[$domain];
        $this->refreshDomainProperties($domain, $record, $refresh);
    }

    public function refreshDomainProperties(object $domain, $record, SplObjectStorage $refresh)
    {
        foreach ($this->properties as $name => $prop) {
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
        $field = $this->caseConverter->fromDomainToSource($name);

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

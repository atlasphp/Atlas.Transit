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

        // on further consideration, we should extract only the properties
        // that have constructor params for them. that keeps internal-only
        // properties from being extracted.
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
        if (! class_exists($class)) {
            throw new Exception("Typehint for \$"
                . $param->getName()
                . " of class {$class} does not exist."
            );
        }

        $rclass = new ReflectionClass($class);
        if ($rclass->hasMethod('__transitFromSource')) {
            $rmethod = $rclass->getMethod('__transitFromSource');
            $rmethod->setAccessible(true);
            return $rmethod->invoke(null, $record, $field);
        }

        $paramCount = 0;
        $rctor = $rclass->getConstructor();
        if ($rctor !== null) {
            $paramCount = $rctor->getNumberOfParameters();
        }

        if ($paramCount == 0) {
            return new $class();
        }

        return new $class($datum);
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

            $field = $this->caseConverter->fromDomainToSource($name);
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

        $class = get_class($datum);
        $rclass = new ReflectionClass($class);

        if ($rclass->hasMethod('__transitIntoSource')) {
            $rmethod = $rclass->getMethod('__transitIntoSource');
            $rmethod->setAccessible(true);
            $rmethod->invoke($datum, $record, $field);
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

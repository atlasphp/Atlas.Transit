<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Record;
use Atlas\Transit\DataConverter;
use Atlas\Transit\Exception;
use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;

class EntityHandler extends Handler
{
    protected $parameters = [];
    protected $properties = [];
    protected $types = [];
    protected $classes = [];
    protected $dataConverter;
    protected $autoincColumn;

    public function __construct(string $domainClass, string $mapperClass)
    {
        parent::__construct($domainClass, $mapperClass);

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

        $tableClass = $this->mapperClass . 'Table';
        $this->autoincColumn = $tableClass::AUTOINC_COLUMN;

        /** @todo allow for factories and dependency injection */
        $dataConverter = $this->domainClass . 'DataConverter';
        if (! class_exists($dataConverter)) {
            $dataConverter = DataConverter::CLASS;
        }
        $this->dataConverter = new $dataConverter();
    }

    public function getSourceMethod(string $method) : string
    {
        return $method . 'Record';
    }

    public function getDomainMethod(string $method) : string
    {
        return $method . 'Entity';
    }

    public function getParameters() : array
    {
        return $this->parameters;
    }

    public function getProperties() : array
    {
        return $this->properties;
    }

    public function getDataConverter() : DataConverter
    {
        return $this->dataConverter;
    }

    public function isAutoincColumn($field) : bool
    {
        return $this->autoincColumn === $field;
    }

    public function getType(string $name)
    {
        return $this->types[$name];
    }

    public function getClass(string $name)
    {
        return $this->classes[$name];
    }

    public function newDomain($transit, $record)
    {
        $data = $this->convertSourceData($transit, $record);

        $args = [];
        foreach ($this->parameters as $name => $param) {
            $args[] = $this->newDomainArgument($transit, $param, $record, $data);
        }

        $domainClass = $this->domainClass;
        return new $domainClass(...$args);
    }

    protected function newDomainArgument(
        $transit,
        ReflectionParameter $param,
        Record $record,
        array $data
    ) {
        $name = $param->getName();
        $datum = $data[$name];

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

        // value object => matching class: leave as is
        if ($datum instanceof $class) {
            return $datum;
        }

        // any value => a class
        $subhandler = $transit->getHandler($class);
        if ($subhandler !== null) {
            // use subhandler for domain object
            return $transit->_newDomain($subhandler, $datum);
        }

        // @todo report the domain class and what converter was being used
        throw new Exception("No handler for \$" . $param->getName() . " typehint of {$class}.");
    }

    protected function convertSourceData($transit, Record $record) : array
    {
        $data = [];

        foreach ($this->parameters as $name => $param) {

            // custom approach
            $method = "__{$name}FromSource";
            if (method_exists($this->dataConverter, $method)) {
                $data[$name] = $this->dataConverter->$method($record);
                continue;
            }

            // default approach
            $field = $transit->caseConverter->fromDomainToSource($name);
            if ($record->has($field)) {
                $data[$name] = $record->$field;
            } elseif ($param->isDefaultValueAvailable()) {
                $data[$name] = $param->getDefaultValue();
            } else {
                $data[$name] = null;
            }
        }

        return $data;
    }

    public function updateSource($transit, $domain, $record) : void
    {
        $data = [];
        foreach ($this->getProperties() as $name => $property) {

            // custom approach
            $custom = "__{$name}IntoSource";
            if (method_exists($this->dataConverter, $custom)) {
                $this->dataConverter->$custom($record, $property->getValue($domain));
                continue;
            }

            $datum = $this->updateSourceDatum(
                $transit,
                $domain,
                $record,
                $property->getValue($domain)
            );

            $field = $transit->caseConverter->fromDomainToSource($name);
            if ($record->has($field)) {
                $record->$field = $datum;
            }
        }
    }

    // basically, we look to see if the $datum has a handler or not.
    // if it does, we update the $datum as well.
    protected function updateSourceDatum(
        $transit,
        $domain,
        Record $record,
        $datum
    ) {
        if (! is_object($datum)) {
            return $datum;
        }

        $handler = $transit->getHandler($datum);
        if ($handler !== null) {
            return $transit->updateSource($datum);
        }

        return $datum;
    }

    public function refreshDomain($transit, $domain, $record, $storage, $refresh)
    {
        foreach ($this->properties as $name => $prop) {
            $this->refreshDomainProperty($transit, $prop, $domain, $record, $storage, $refresh);
        }

        $refresh->detach($domain);
    }

    protected function refreshDomainProperty(
        $transit,
        ReflectionProperty $prop,
        $domain,
        $record,
        $storage,
        $refresh
    ) : void
    {
        $name = $prop->getName();
        $field = $transit->caseConverter->fromDomainToSource($name);

        if ($this->isAutoincColumn($field)) {
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
        $subhandler = $transit->getHandler($class);
        if ($subhandler !== null) {
            // because there may be domain objects not created through Transit
            $record = $storage[$datum];
            $subhandler->refreshDomain($transit, $datum, $record, $storage, $refresh);
            return;
        }
    }
}

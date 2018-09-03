<?php
namespace Atlas\Transit\Handler;

use ReflectionClass;
use Atlas\Transit\DataConverter;

class EntityHandler extends Handler
{
    protected $parameters = [];
    protected $properties = [];
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
            $this->parameters[$rparam->getName()] = $rparam;
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

    public function getAutoincColumn() : ?string
    {
        return $this->autoincColumn;
    }

    public function new(array $args)
    {
        $domainClass = $this->domainClass;
        return new $domainClass(...$args);
    }
}

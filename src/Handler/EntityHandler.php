<?php
namespace Atlas\Transit\Handler;

use ReflectionClass;
use Atlas\Transit\DataConverter;

class EntityHandler extends Handler
{
    protected $parameters = [];
    protected $properties = [];
    protected $dataConverter;

    public function __construct(string $domainClass, string $entityNamespace, string $sourceNamespace)
    {
        $this->domainClass = $domainClass;

        $rclass = new ReflectionClass($this->domainClass);

        foreach ($rclass->getProperties() as $rprop) {
            $rprop->setAccessible(true);
            $this->properties[$rprop->getName()] = $rprop;
        }

        $rmethod = $rclass->getMethod('__construct');
        foreach ($rmethod->getParameters($rmethod) as $rparam) {
            $this->parameters[$rparam->getName()] = $rparam;
        }

        $dataConverter = $this->domainClass . 'Converter';
        if (! class_exists($dataConverter)) {
            $dataConverter = DataConverter::CLASS;
        }
        $this->dataConverter = new $dataConverter();

        $this->setMapperClass($domainClass, $entityNamespace, $sourceNamespace);
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

    public function getConverter() : DataConverter
    {
        return $this->dataConverter;
    }

    public function new(array $args)
    {
        $domainClass = $this->domainClass;
        return new $domainClass(...$args);
    }
}

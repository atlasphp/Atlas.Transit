<?php
namespace Atlas\Transit\Handler;

use ReflectionClass;
use Atlas\Transit\DataConverter;

class EntityHandler extends Handler
{
    protected $parameters = [];
    protected $properties = [];
    protected $converter;

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

        $converter = $this->domainClass . 'Converter';
        if (! class_exists($converter)) {
            $converter = DataConverter::CLASS;
        }
        $this->converter = new $converter();

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

    public function getParameters()
    {
        return $this->parameters;
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function getConverter()
    {
        return $this->converter;
    }
}

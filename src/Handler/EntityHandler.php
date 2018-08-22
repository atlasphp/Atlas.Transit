<?php
namespace Atlas\Transit\Handler;

use Atlas\Orm\Mapper\Record;
use ReflectionClass;

class EntityHandler extends Handler
{
    protected $parameters;
    protected $properties;

    public function __construct(string $mapperClass, string $domainClass)
    {
        $this->domainClass = $domainClass;
        $this->mapperClass = $mapperClass;
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
        if ($this->parameters === null) {
            $rclass = new ReflectionClass($this->domainClass);
            $rmethod = $rclass->getMethod('__construct');
            foreach ($rmethod->getParameters($rmethod) as $rparam) {
                $this->parameters[$rparam->getName()] = $rparam;
            }
        }

        return $this->parameters;
    }

    public function getProperties()
    {
        if ($this->properties === null) {
            $this->properties = [];
            $rclass = new ReflectionClass($this->domainClass);
            foreach ($rclass->getProperties() as $rprop) {
                $rprop->setAccessible(true);
                $this->properties[$rprop->getName()] = $rprop;
            }
        }

        return $this->properties;
    }
}

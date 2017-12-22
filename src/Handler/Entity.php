<?php
namespace Atlas\Transit\Handler;

use Atlas\Orm\Mapper\Record;
use ReflectionClass;

class Entity extends Handler
{
    protected $parameters;
    protected $properties;

    protected $domainFromRecord = [];
    protected $recordFromDomain = [];

    public function __construct(string $domainClass, string $mapperClass, array $domainFromRecord = [])
    {
        $this->domainClass = $domainClass;
        $this->mapperClass = $mapperClass;
        $this->domainFromRecord = $domainFromRecord;
        $this->recordFromDomain = array_flip($domainFromRecord);
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
            $this->parameters = $rmethod->getParameters($rmethod);
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

    public function getDomainFromRecord($name)
    {
        return $this->domainFromRecord[$name] ?? null;
    }

    public function getRecordFromDomain($field)
    {
        return $this->recordFromDomain[$field] ?? null;
    }
}

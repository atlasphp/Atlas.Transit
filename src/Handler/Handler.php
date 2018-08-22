<?php
namespace Atlas\Transit\Handler;

abstract class Handler
{
    protected $domainClass;

    protected $mapperClass;

    public function getDomainClass() : string
    {
        return $this->domainClass;
    }

    public function getMapperClass() : string
    {
        if ($this->mapperClass === null) {
            throw new Exception("no source mapper class for {$this->domainClass}");
        }

        return $this->mapperClass;
    }

    abstract public function getSourceMethod(string $method) : string;

    abstract public function getDomainMethod(string $method) : string;
}

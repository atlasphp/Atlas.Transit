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
        return $this->mapperClass;
    }

    abstract public function getSourceMethod(string $method) : string;

    abstract public function getDomainMethod(string $method) : string;
}

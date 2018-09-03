<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

abstract class Handler
{
    protected $domainClass;

    protected $mapperClass;

    public function __construct(string $domainClass, string $mapperClass)
    {
        $this->domainClass = $domainClass;
        $this->mapperClass = $mapperClass;
    }

    public function getMapperClass() : string
    {
        return $this->mapperClass;
    }

    abstract public function getSourceMethod(string $method) : string;

    abstract public function getDomainMethod(string $method) : string;
}

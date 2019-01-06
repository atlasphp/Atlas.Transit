<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

abstract class Handler
{
    protected $domainClass;

    protected $mapperClass;

    protected $handlerLocator;

    public function __construct(string $domainClass, string $mapperClass, $handlerLocator)
    {
        $this->domainClass = $domainClass;
        $this->mapperClass = $mapperClass;
        $this->handlerLocator = $handlerLocator;
    }

    public function getMapperClass() : string
    {
        return $this->mapperClass;
    }

    abstract public function getSourceMethod(string $method) : string;

    abstract public function newDomain($transit, $source);

    abstract public function updateSource($transit, $domain, $source);

    abstract public function refreshDomain($transit, $domain, $record, $storage, $refresh);
}

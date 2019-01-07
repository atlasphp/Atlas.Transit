<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Mapper;
use Atlas\Transit\Transit;

abstract class Handler
{
    protected $domainClass;

    protected $mapperClass;

    protected $handlerLocator;

    protected $mapper;

    public function __construct(
        string $domainClass,
        Mapper $mapper,
        HandlerLocator $handlerLocator
    ) {
        $this->domainClass = $domainClass;
        $this->mapper = $mapper;
        $this->mapperClass = get_class($mapper);
        $this->handlerLocator = $handlerLocator;
    }

    public function getMapperClass() : string
    {
        return $this->mapperClass;
    }

    abstract public function newSource($domain, $storage, $refresh) : object;

    abstract public function newDomain($source, $storage);

    abstract public function updateSource(object $domain, $storage, $refresh);

    abstract public function refreshDomain(object $domain, $record, $storage, $refresh);
}

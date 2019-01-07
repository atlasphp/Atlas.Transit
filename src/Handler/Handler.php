<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Transit\Transit;

abstract class Handler
{
    protected $domainClass;

    protected $mapperClass;

    protected $handlerLocator;

    public function __construct(
        string $domainClass,
        string $mapperClass,
        HandlerLocator $handlerLocator
    ) {
        $this->domainClass = $domainClass;
        $this->mapperClass = $mapperClass;
        $this->handlerLocator = $handlerLocator;
    }

    public function getMapperClass() : string
    {
        return $this->mapperClass;
    }

    abstract public function getSourceMethod(string $method) : string;

    abstract public function newDomain($source, $storage);

    abstract public function updateSource(Transit $transit, object $domain, $source);

    abstract public function refreshDomain(Transit $transit, object $domain, $record, $storage, $refresh);
}

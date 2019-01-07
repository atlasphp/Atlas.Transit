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

    public function __construct(
        string $domainClass,
        Mapper $mapper,
        HandlerLocator $handlerLocator
    ) {
        $this->domainClass = $domainClass;
        $this->mapper = $mapper;
        $this->handlerLocator = $handlerLocator;
    }

    public function newSelect(array $whereEquals = [])
    {
        return $this->mapper->select($whereEquals);
    }

    abstract public function newSource() : object;

    abstract public function newDomain($source, $storage);

    abstract public function updateSource(Transit $transit, object $domain, $source);

    abstract public function refreshDomain(object $domain, $record, $storage, $refresh);
}

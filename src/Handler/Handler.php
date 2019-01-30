<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Mapper;
use Atlas\Transit\Exception;
use Atlas\Transit\Transit;
use Atlas\Transit\Reflection\Reflection;
use SplObjectStorage;

abstract class Handler
{
    protected $reflection;

    protected $handlerLocator;

    protected $mapper;

    protected $storage;

    public function __construct(
        Reflection $reflection,
        Mapper $mapper,
        HandlerLocator $handlerLocator,
        SplObjectStorage $storage
    ) {
        $this->reflection = $reflection;
        $this->mapper = $mapper;
        $this->handlerLocator = $handlerLocator;
        $this->storage = $storage;
    }

    public function getMapperClass() : string
    {
        return $this->reflection->mapperClass;
    }

    abstract public function newSource(object $domain, SplObjectStorage $refresh) : object;

    abstract public function newDomain($source);

    abstract public function updateSource(object $domain, SplObjectStorage $refresh);

    abstract public function refreshDomain(object $domain, SplObjectStorage $refresh);

    public function deleteSource(object $domain, SplObjectStorage $refresh)
    {
        if (! $this->storage->contains($domain)) {
            throw new Exception("no source for domain");
        }

        $source = $this->storage[$domain];
        $source->setDelete();

        $refresh->detach($domain);

        return $source;
    }
}

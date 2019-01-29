<?php
namespace Atlas\Transit\Handler;

use Atlas\Orm\Atlas;
use Atlas\Transit\Exception;
use Atlas\Transit\Reflection\reflectionLocator;
use SplObjectStorage;

class HandlerLocator
{
    protected $instances = [];

    protected $storage;

    protected $reflectionLocator;

    public function __construct(
        Atlas $atlas,
        ReflectionLocator $reflectionLocator
    ) {
        $this->atlas = $atlas;
        $this->reflectionLocator = $reflectionLocator;
        $this->storage = new SplObjectStorage();
    }

    public function getStorage() : SplObjectStorage
    {
        return $this->storage;
    }

    public function get($domainClass) // : Handler
    {
        if (is_object($domainClass)) {
            $domainClass = get_class($domainClass);
        }

        if (! class_exists($domainClass)) {
            throw new Exception("Domain class '{$domainClass}' does not exist.");
        }

        if (! array_key_exists($domainClass, $this->instances)) {
            $this->instances[$domainClass] = $this->newHandler($domainClass);
        }

        return $this->instances[$domainClass];
    }

    protected function newHandler(string $domainClass) // : ?Handler
    {
        $r = $this->reflectionLocator->get($domainClass);

        if ($r->type === null) {
            throw new Exception("Class '$domainClass' not annotated for Transit.");
        }

        $method = 'new' . $r->type;
        return $this->$method($r);
    }

    protected function newEntity(object $r) : EntityHandler
    {
        return new EntityHandler(
            $r,
            $this->atlas->mapper($r->mapperClass),
            $this,
            $this->storage
        );
    }

    protected function newCollection(object $r) : CollectionHandler
    {
        return new CollectionHandler(
            $r,
            $this->atlas->mapper($r->mapperClass),
            $this,
            $this->storage
        );
    }

    protected function newAggregate(object $r) : AggregateHandler
    {
        return new AggregateHandler(
            $r,
            $this->atlas->mapper($r->mapperClass),
            $this,
            $this->storage
        );
    }

    protected function newValueObject(object $r) : ValueObjectHandler
    {
        return new ValueObjectHandler($r);
    }
}

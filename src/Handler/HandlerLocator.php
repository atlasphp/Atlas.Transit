<?php
namespace Atlas\Transit\Handler;

use Atlas\Orm\Atlas;
use Atlas\Transit\Exception;
use Atlas\Transit\Inflector;
use Atlas\Transit\Reflections;
use SplObjectStorage;

class HandlerLocator
{
    protected $instances = [];

    protected $storage;

    protected $valueObjectHandler;

    protected $reflections;

    public function __construct(
        Atlas $atlas,
        string $sourceNamespace,
        Inflector $inflector
    ) {
        $this->atlas = $atlas;
        $this->inflector = $inflector;
        $this->storage = new SplObjectStorage();
        $this->valueObjectHandler = new ValueObjectHandler($inflector);
        $this->reflections = new Reflections($inflector, $sourceNamespace);
    }

    public function getStorage() : SplObjectStorage
    {
        return $this->storage;
    }

    public function getOrThrow($spec) : Handler
    {
        if (is_object($spec)) {
            $spec = get_class($spec);
        }

        $handler = $this->getByClass($spec);

        if ($handler === null) {
            throw new Exception("No handler for class '$spec'.");
        }

        return $handler;
    }

    public function get($spec) : ?Handler
    {
        if (is_object($spec)) {
            $spec = get_class($spec);
        }

        return $this->getByClass($spec);
    }

    protected function getByClass(string $domainClass) : ?Handler
    {
        if (! class_exists($domainClass)) {
            throw new Exception("Domain class '{$domainClass}' does not exist.");
        }

        if (! array_key_exists($domainClass, $this->instances)) {
            $this->instances[$domainClass] = $this->newHandler($domainClass);
        }

        return $this->instances[$domainClass];
    }

    protected function newHandler(string $domainClass) : ?Handler
    {
        $r = $this->reflections->get($domainClass);
        if ($r->type === null) {
            return null;
        }

        $method = 'new' . $r->type;
        return $this->$method($r);
    }

    protected function newEntity(object $r) : EntityHandler
    {
        return new EntityHandler(
            $r->domainClass,
            $r,
            $this->atlas->mapper($r->mapperClass),
            $this,
            $this->storage,
            $this->valueObjectHandler
        );
    }

    protected function newCollection(object $r) : CollectionHandler
    {
        return new CollectionHandler(
            $r->domainClass,
            $this->atlas->mapper($r->mapperClass),
            $this,
            $this->storage
        );
    }

    protected function newAggregate(object $r) : AggregateHandler
    {
        return new AggregateHandler(
            $r->domainClass,
            $r,
            $this->atlas->mapper($r->mapperClass),
            $this,
            $this->storage,
            $this->valueObjectHandler
        );
    }
}

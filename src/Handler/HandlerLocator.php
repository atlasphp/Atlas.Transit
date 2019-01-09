<?php
namespace Atlas\Transit\Handler;

use Atlas\Orm\Atlas;
use Atlas\Transit\CaseConverter;
use Atlas\Transit\Casing\CamelCase;
use Atlas\Transit\Casing\SnakeCase;
use Atlas\Transit\DataConverter;
use Atlas\Transit\Exception;
use ReflectionClass;
use SplObjectStorage;

class HandlerLocator
{
    protected $instances = [];

    protected $sourceNamespace;

    protected $entityNamespace;

    protected $entityNamespaceLen;

    protected $aggregateNamespace;

    protected $aggregateNamespaceLen;

    protected $caseConverter;

    protected $storage;

    public function __construct(
        Atlas $atlas,
        string $sourceNamespace,
        string $domainNamespace,
        CaseConverter $caseConverter
    ) {
        $this->atlas = $atlas;
        $this->sourceNamespace = rtrim($sourceNamespace, '\\') . '\\';
        $this->entityNamespace = rtrim($domainNamespace, '\\') . '\\Entity\\';
        $this->entityNamespaceLen = strlen($this->entityNamespace);
        $this->aggregateNamespace = rtrim($domainNamespace, '\\') . '\\Aggregate\\';
        $this->aggregateNamespaceLen = strlen($this->aggregateNamespace);
        $this->caseConverter = $caseConverter;
        $this->storage = new SplObjectStorage();
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
            throw new Exception("No handler for class '$domainClass'.");
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
        return $this->newEntityOrCollection($domainClass)
            ?? $this->newAggregate($domainClass)
            ?? null;
    }

    protected function newEntityOrCollection(string $domainClass) : ?Handler
    {
        $isEntity = $this->entityNamespace == substr(
            $domainClass, 0, $this->entityNamespaceLen
        );

        if (! $isEntity) {
            return null;
        }

        $mapperClass = $this->getMapperClassForEntity($domainClass);
        $mapper = $this->atlas->mapper($mapperClass);

        if (substr($domainClass, -10) == 'Collection') {
            return new CollectionHandler(
                $domainClass,
                $mapper,
                $this,
                $this->storage
            );
        }

        /* @todo allow for factory/di */
        $dataConverter = $domainClass . 'DataConverter';
        if (! class_exists($dataConverter)) {
            $dataConverter = DataConverter::CLASS;
        }

        return new EntityHandler(
            $domainClass,
            $mapper,
            $this,
            $this->storage,
            $this->caseConverter,
            new $dataConverter()
        );
    }

    protected function getMapperClassForEntity(string $domainClass) : string
    {
        $class = $this->sourceNamespace . substr(
            $domainClass, $this->entityNamespaceLen
        );
        $parts = explode('\\', $class);
        array_pop($parts);
        $final = end($parts);
        return implode('\\', $parts) . '\\' . $final;
    }

    protected function newAggregate(string $domainClass) : ?Handler
    {
        $isAggregate = $this->aggregateNamespace == substr(
            $domainClass, 0, $this->aggregateNamespaceLen
        );

        if (! $isAggregate) {
            return null;
        }

        $rootClass = (new ReflectionClass($domainClass))
            ->getMethod('__construct')
            ->getParameters()[0]
            ->getClass()
            ->getName();

        $mapperClass = $this->getMapperClassForEntity($rootClass);
        $mapper = $this->atlas->mapper($mapperClass);

        /* @todo allow for factory/di */
        $dataConverter = $domainClass . 'DataConverter';
        if (! class_exists($dataConverter)) {
            $dataConverter = DataConverter::CLASS;
        }

        return new AggregateHandler(
            $domainClass,
            $mapper,
            $this,
            $this->storage,
            $this->caseConverter,
            new $dataConverter()
        );
    }
}

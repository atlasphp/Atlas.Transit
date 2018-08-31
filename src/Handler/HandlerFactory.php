<?php
namespace Atlas\Transit\Handler;

use ReflectionClass;

class HandlerFactory
{
    protected $sourceNamespace;

    protected $entityNamespace;

    protected $entityNamespaceLen;

    protected $aggregateNamespace;

    protected $aggregateNamespaceLen;

    public function __construct(
        string $sourceNamespace,
        string $domainNamespace
    ) {
        $this->sourceNamespace = rtrim($sourceNamespace, '\\') . '\\';
        $this->entityNamespace = rtrim($domainNamespace, '\\') . '\\Entity\\';
        $this->entityNamespaceLen = strlen($this->entityNamespace);
        $this->aggregateNamespace = rtrim($domainNamespace, '\\') . '\\Aggregate\\';
        $this->aggregateNamespaceLen = strlen($this->aggregateNamespace);
    }

    public function new(string $domainClass) : ?Handler
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

        $handlerClass = EntityHandler::CLASS;
        if (substr($domainClass, -10) == 'Collection') {
            $handlerClass = CollectionHandler::CLASS;
        }

        return new $handlerClass(
            $domainClass,
            $this->getMapperClassForEntity($domainClass)
        );
    }

    protected function getMapperClassForEntity($domainClass)
    {
        $class = $this->sourceNamespace . substr(
            $domainClass, $this->entityNamespaceLen
        );
        $parts = explode('\\', $class);
        array_pop($parts);
        $final = end($parts);
        return implode('\\', $parts) . '\\' . $final;
    }

    protected function newAggregate($domainClass)
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

        return new AggregateHandler(
            $domainClass,
            $this->getMapperClassForEntity($rootClass)
        );
    }
}

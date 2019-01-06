<?php
namespace Atlas\Transit\Handler;

use Atlas\Transit\CaseConverter;
use Atlas\Transit\Casing\CamelCase;
use Atlas\Transit\Casing\SnakeCase;
use Atlas\Transit\Exception;
use ReflectionClass;

class HandlerLocator
{
    protected $instances = [];

    protected $sourceNamespace;

    protected $entityNamespace;

    protected $entityNamespaceLen;

    protected $aggregateNamespace;

    protected $aggregateNamespaceLen;

    protected $caseConverter;

    public function __construct(
        string $sourceNamespace,
        string $domainNamespace,
        CaseConverter $caseConverter
    ) {
        $this->sourceNamespace = rtrim($sourceNamespace, '\\') . '\\';
        $this->entityNamespace = rtrim($domainNamespace, '\\') . '\\Entity\\';
        $this->entityNamespaceLen = strlen($this->entityNamespace);
        $this->aggregateNamespace = rtrim($domainNamespace, '\\') . '\\Aggregate\\';
        $this->aggregateNamespaceLen = strlen($this->aggregateNamespace);
        $this->caseConverter = $caseConverter;
    }

    public function get(string $domainClass) : ?Handler
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

        if (substr($domainClass, -10) == 'Collection') {
            return new CollectionHandler(
                $domainClass,
                $mapperClass,
                $this
            );
        }

        return new EntityHandler(
            $domainClass,
            $mapperClass,
            $this,
            $this->caseConverter
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

        return new AggregateHandler(
            $domainClass,
            $mapperClass,
            $this,
            $this->caseConverter
        );
    }
}

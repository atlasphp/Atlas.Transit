<?php
namespace Atlas\Transit\Handler;

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
        $this->sourceNamespace = $sourceNamespace;
        $this->domainNamespace = $domainNamespace;
        $this->sourceNamespace = rtrim($sourceNamespace, '\\') . '\\';
        $this->entityNamespace = rtrim($domainNamespace, '\\') . '\\Entity\\';
        $this->entityNamespaceLen = strlen($this->entityNamespace);
        $this->aggregateNamespace = rtrim($domainNamespace, '\\') . '\\Aggregate\\';
        $this->aggregateNamespaceLen = strlen($this->aggregateNamespace);
    }

    public function new(string $domainClass) : ?Handler
    {
        $isEntity = $this->entityNamespace == substr(
            $domainClass, 0, $this->entityNamespaceLen
        );

        if ($isEntity) {

            $handlerClass = EntityHandler::CLASS;
            if (substr($domainClass, -10) == 'Collection') {
                $handlerClass = CollectionHandler::CLASS;
            }

            return new $handlerClass(
                $domainClass,
                $this->entityNamespace,
                $this->sourceNamespace
            );
        }

        $isAggregate = $this->aggregateNamespace == substr(
            $domainClass, 0, $this->aggregateNamespaceLen
        );

        if ($isAggregate) {
            return new AggregateHandler(
                $domainClass,
                $this->aggregateNamespace,
                $this->sourceNamespace
            );
        }

        return null;
    }
}

<?php
namespace Atlas\Transit\Handler;

use Atlas\Mapper\Record;
use Atlas\Mapper\RecordSet;
use Closure;
use ReflectionClass;
use SplObjectStorage;

class CollectionHandler extends Handler
{
    protected $memberClass;

    public function __construct(string $domainClass, string $entityNamespace, string $sourceNamespace)
    {
        $this->domainClass = $domainClass;
        $this->memberClass = substr($domainClass, 0, -10); // strip Collection from class name
        $this->setMapperClass($domainClass, $entityNamespace, $sourceNamespace);
    }

    public function getSourceMethod(string $method) : string
    {
        return $method . 'RecordSet';
    }

    public function getDomainMethod(string $method) : string
    {
        return $method . 'Collection';
    }

    public function getMemberClass(Record $record)
    {
        return $this->memberClass;
    }

    public function new(array $members)
    {
        $domainClass = $this->domainClass;
        return new $domainClass($members);
    }
}

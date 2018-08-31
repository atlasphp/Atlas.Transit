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

    public function __construct(string $domainClass, string $mapperClass)
    {
        parent::__construct($domainClass, $mapperClass);
        $this->memberClass = substr($domainClass, 0, -10); // strip Collection from class name
    }

    public function getSourceMethod(string $method) : string
    {
        return $method . 'RecordSet';
    }

    public function getDomainMethod(string $method) : string
    {
        return $method . 'Collection';
    }

    /**
     * @todo Allow for different member classes based on Record types/values.
     */
    public function getMemberClass(Record $record) : string
    {
        return $this->memberClass;
    }

    public function new(array $members)
    {
        $domainClass = $this->domainClass;
        return new $domainClass($members);
    }
}

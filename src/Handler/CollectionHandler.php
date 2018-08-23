<?php
namespace Atlas\Transit\Handler;

use Atlas\Mapper\Record;
use Atlas\Mapper\RecordSet;
use Closure;
use ReflectionClass;
use SplObjectStorage;

/*

// contains one kind of domain class
$transit->mapCollection(ThreadCollection::CLASS, ThreadEntity::CLASS);

// contains any kind of domain class
$transit
    ->mapCollection(MultiCollection::CLASS)
    ->setMemberClass(function ($record) {
        switch (true) {
            case $record instanceof FooRecord:
                return FooEntity::CLASS;
            case $record instanceof BarRecord:
                return BarEntity::CLASS;
            case $record instanceof Baz:
                return BazEntity::CLASS;
            default:
                throw new \Exception("Unknown record type.")
        }
    });
*/
class CollectionHandler extends Handler
{
    protected $memberClass;

    public function __construct(string $mapperClass, string $domainClass)
    {
        $this->domainClass = $domainClass;
        $this->mapperClass = $mapperClass;

        if (substr($domainClass, -10) == 'Collection') {
            $defaultMemberClass = substr($domainClass, 0, -10);
            $this->setMemberClass($defaultMemberClass);
        }
    }

    public function getSourceMethod(string $method) : string
    {
        return $method . 'RecordSet';
    }

    public function getDomainMethod(string $method) : string
    {
        return $method . 'Collection';
    }

    public function setMemberClass($memberClass)
    {
        $this->memberClass = $memberClass;
        return $this;
    }

    public function getMemberClass(Record $record)
    {
        if (is_string($this->memberClass)) {
            return $this->memberClass;
        }

        if ($this->memberClass instanceof Closure) {
            return call_user_func($this->member_class, $record);
        }

        throw new Exception("cannot get member class");
    }
}

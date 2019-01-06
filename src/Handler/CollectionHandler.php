<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Record;
use Atlas\Mapper\RecordSet;
use Closure;
use ReflectionClass;
use SplObjectStorage;

class CollectionHandler extends Handler
{
    protected $memberClass;

    public function __construct(string $domainClass, string $mapperClass, $handlerLocator)
    {
        parent::__construct($domainClass, $mapperClass, $handlerLocator);
        $this->memberClass = substr($domainClass, 0, -10); // strip Collection from class name
    }

    public function getSourceMethod(string $method) : string
    {
        return $method . 'RecordSet';
    }

    /**
     * @todo Allow for different member classes based on Record types/values.
     */
    public function getMemberClass(Record $record) : string
    {
        return $this->memberClass;
    }

    public function newDomain($transit, $recordSet)
    {
        $members = [];
        foreach ($recordSet as $record) {
            $memberClass = $this->getMemberClass($record);
            $members[] = $transit->newDomain($memberClass, $record);
        }

        $domainClass = $this->domainClass;
        return new $domainClass($members);
    }

    public function updateSource($transit, $domain, $recordSet)
    {
        $recordSet->detachAll();
        foreach ($domain as $member) {
            $record = $transit->updateSource($member);
            $recordSet[] = $record;
        }
    }

    public function refreshDomain($transit, $collection, $recordSet, $storage, $refresh)
    {
        foreach ($collection as $member) {
            $handler = $this->handlerLocator->get($member);
            $source = $storage[$member];
            $handler->refreshDomain($transit, $member, $source, $storage, $refresh);
        }

        $refresh->detach($collection);
    }
}

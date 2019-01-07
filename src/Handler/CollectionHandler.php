<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Record;
use Atlas\Mapper\RecordSet;
use Atlas\Transit\Transit;
use Closure;
use ReflectionClass;
use SplObjectStorage;

class CollectionHandler extends Handler
{
    protected $memberClass;

    public function __construct(
        string $domainClass,
        string $mapperClass,
        HandlerLocator $handlerLocator
    ) {
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

    public function newDomain($recordSet, $storage)
    {
        $members = [];
        foreach ($recordSet as $record) {
            $memberClass = $this->getMemberClass($record);
            $memberHandler = $this->handlerLocator->get($memberClass);
            $members[] = $memberHandler->newDomain($record, $storage);
        }

        $domainClass = $this->domainClass;
        $domain = new $domainClass($members);
        $storage->attach($domain, $recordSet);
        return $domain;
    }

    public function updateSource(Transit $transit, object $collection, $recordSet)
    {
        $recordSet->detachAll();
        foreach ($collection as $member) {
            $record = $transit->updateSource($member);
            $recordSet[] = $record;
        }
    }

    public function refreshDomain(Transit $transit, object $collection, $recordSet, $storage, $refresh)
    {
        foreach ($collection as $member) {
            $handler = $this->handlerLocator->get(get_class($member));
            $source = $storage[$member];
            $handler->refreshDomain($transit, $member, $source, $storage, $refresh);
        }

        $refresh->detach($collection);
    }
}

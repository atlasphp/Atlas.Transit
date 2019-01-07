<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Mapper;
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
        Mapper $mapper,
        HandlerLocator $handlerLocator,
        SplObjectStorage $storage
    ) {
        parent::__construct($domainClass, $mapper, $handlerLocator, $storage);
        $this->memberClass = substr($domainClass, 0, -10); // strip Collection from class name
    }

    public function newSource($domain, SplObjectStorage $storage, SplObjectStorage $refresh) : object
    {
        $source = $this->mapper->newRecordSet();
        $this->storage->attach($domain, $source);
        $refresh->attach($domain);
        return $source;
    }

    /**
     * @todo Allow for different member classes based on Record types/values.
     */
    public function getMemberClass(Record $record) : string
    {
        return $this->memberClass;
    }

    public function newDomain($recordSet, SplObjectStorage $storage)
    {
        $members = [];
        foreach ($recordSet as $record) {
            $memberClass = $this->getMemberClass($record);
            $memberHandler = $this->handlerLocator->get($memberClass);
            $members[] = $memberHandler->newDomain($record, $this->storage);
        }

        $domainClass = $this->domainClass;
        $domain = new $domainClass($members);
        $this->storage->attach($domain, $recordSet);
        return $domain;
    }

    public function updateSource(object $domain, SplObjectStorage $refresh)
    {
        if (! $this->storage->contains($domain)) {
            $source = $this->newSource($domain, $this->storage, $refresh);
        }

        $recordSet = $this->storage[$domain];
        $recordSet->detachAll();

        foreach ($domain as $member) {
            $handler = $this->handlerLocator->get($member);
            $record = $handler->updateSource($member, $refresh);
            $recordSet[] = $record;
        }

        return $recordSet;
    }

    public function refreshDomain(object $collection, $recordSet, SplObjectStorage $refresh)
    {
        foreach ($collection as $member) {
            $handler = $this->handlerLocator->get($member);
            $source = $this->storage[$member];
            $handler->refreshDomain($member, $source, $refresh);
        }

        $refresh->detach($collection);
    }
}

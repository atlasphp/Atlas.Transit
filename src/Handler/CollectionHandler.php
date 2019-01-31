<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Mapper;
use Atlas\Mapper\Record;
use Atlas\Mapper\RecordSet;
use Atlas\Transit\Transit;
use Closure;
use Atlas\Transit\Reflection\CollectionReflection;
use SplObjectStorage;

class CollectionHandler extends MappedHandler
{
    public function newSource(object $domain, SplObjectStorage $refresh) : object
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
        return $this->reflection->memberClass;
    }

    public function newDomain($recordSet)
    {
        $members = [];
        foreach ($recordSet as $record) {
            $memberClass = $this->getMemberClass($record);
            $memberHandler = $this->handlerLocator->get($memberClass);
            $members[] = $memberHandler->newDomain($record);
        }

        $domainClass = $this->reflection->domainClass;
        $domain = new $domainClass($members);
        $this->storage->attach($domain, $recordSet);
        return $domain;
    }

    public function updateSource(object $domain, SplObjectStorage $refresh)
    {
        if (! $this->storage->contains($domain)) {
            $source = $this->newSource($domain, $refresh);
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

    public function refreshDomain(object $collection, SplObjectStorage $refresh)
    {
        foreach ($collection as $member) {
            $handler = $this->handlerLocator->get($member);
            $handler->refreshDomain($member, $refresh);
        }

        $refresh->detach($collection);
    }
}

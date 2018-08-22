<?php
namespace Atlas\Transit;

use ArrayObject;
use Atlas\Orm\Atlas;
use Atlas\Mapper\Record;
use Atlas\Mapper\RecordSet;
use Atlas\Transit\CaseConverter;
use Atlas\Transit\Handler\Aggregate;
use Atlas\Transit\Handler\Collection;
use Atlas\Transit\Handler\Entity;
use Atlas\Transit\Handler\Handler;
use Closure;
use ReflectionParameter;
use ReflectionProperty;
use SplObjectStorage;

class Transit
{
    protected $handlers = [];

    protected $storage;

    protected $refresh;

    protected $sourceCase;

    protected $domainCase;

    protected $atlas;

    protected $plan;

    protected $transaction;

    public function __construct(
        Atlas $atlas,
        CaseConverter $sourceCase = null,
        CaseConverter $domainCase = null
    ) {
        $this->atlas = $atlas;

        if ($sourceCase === null) {
            $this->sourceCase = new CaseConverter\SnakeCase();
        }

        if ($domainCase === null) {
            $this->domainCase = new CaseConverter\CamelCase();
        }

        $this->storage = new SplObjectStorage();
        $this->refresh = new SplObjectStorage();
        $this->plan = new SplObjectStorage();
    }

    public function getStorage()
    {
        return $this->storage;
    }

    public function getPlan()
    {
        return $this->plan;
    }

    public function getTransaction()
    {
        return $this->transaction;
    }

    public function mapEntity(
        string $domainClass,
        string $mapperClass,
        array $domainFromRecord = []
    ) : Entity
    {
        return $this->newHandler(
            Entity::CLASS,
            $domainClass,
            $mapperClass,
            $domainFromRecord
        );
    }

    public function mapAggregate(
        string $domainClass,
        string $mapperClass,
        array $domainFromRecord = []
    ) : Aggregate
    {
        return $this->newHandler(
            Aggregate::CLASS,
            $domainClass,
            $mapperClass,
            $domainFromRecord
        );
    }

    public function mapCollection(
        string $domainClass,
        string $mapperClass
    ) : Collection
    {
        return $this->newHandler(
            Collection::CLASS,
            $domainClass,
            $mapperClass
        );
    }

    protected function newHandler(
        string $handlerClass,
        string $domainClass,
        ...$args
    ) : Handler
    {
        $handler = new $handlerClass($domainClass, ...$args);
        $this->handlers[$domainClass] = $handler;
        return $handler;
    }

    protected function getHandler($spec) : Handler
    {
        if (is_string($spec) && isset($this->handlers[$spec])) {
            return $this->handlers[$spec];
        }

        if (! is_object($spec)) {
            $type = gettype($spec);
            throw new Exception("no handler for {$type}");
        }

        $domainClass = get_class($spec);
        if (isset($this->handlers[$domainClass])) {
            return $this->handlers[$domainClass];
        }

        throw new Exception("no handler for {$domainClass}");
    }

    public function select(string $domainClass) : TransitSelect
    {
        $handler = $this->getHandler($domainClass);

        return new TransitSelect(
            $this,
            $this->atlas->select($handler->getMapperClass()),
            $handler->getSourceMethod('fetch'),
            $domainClass
        );
    }

    public function new(string $domainClass, $source)
    {
        $handler = $this->getHandler($domainClass);
        $method = $handler->getDomainMethod('new');
        $domain = $this->$method($handler, $source);

        $this->storage->attach($domain, $source);
        return $domain;
    }

    protected function newEntity(Entity $handler, Record $record)
    {
        $values = [];
        foreach ($handler->getParameters() as $param) {
            $values[] = $this->getEntityValue($param, $handler, $record);
        }

        $domainClass = $handler->getDomainClass();
        return new $domainClass(...$values);
    }

    protected function newAggregate(Aggregate $handler, Record $record)
    {
        $values = [];
        foreach ($handler->getParameters() as $param) {
            $values[] = $this->getAggregateValue($param, $handler, $record);
        }

        $domainClass = $handler->getDomainClass();
        return new $domainClass(...$values);
    }

    protected function newCollection(Collection $handler, RecordSet $recordSet)
    {
        $members = [];
        foreach ($recordSet as $record) {
            $memberClass = $handler->getMemberClass($record);
            $members[] = $this->new($memberClass, $record);
        }

        $domainClass = $handler->getDomainClass();
        return new $domainClass($members);
    }

    protected function getEntityValue(
        ReflectionParameter $param,
        Entity $handler,
        Record $record
    ) {
        $name = $param->getName();

        $field = $handler->getDomainFromRecord($name);
        if ($field === null) {
            $field = $this->domainCase->convert($name, $this->sourceCase);
        }

        if (! $record->has($field)) {
            return $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
        }

        $value = $record->$field;

        $class = $param->getClass();
        if ($class !== null) {
            return $this->new($class->getName(), $value);
        }

        $type = $param->getType();
        if ($type !== null) {
            settype($value, $type);
        }

        return $value;
    }

    protected function getAggregateValue(
        ReflectionParameter $param,
        Aggregate $handler,
        Record $record
    ) {
        if ($handler->isRoot($param)) {
            return $this->new($param->getClass()->getName(), $record);
        }

        return $this->getEntityValue($param, $handler, $record);
    }

    protected function updateSource($domain)
    {
        $handler = $this->getHandler($domain);

        if (! $this->storage->contains($domain)) {
            $this->addSource($handler, $domain);
        }
        $source = $this->storage[$domain];

        $updater = $handler->getUpdater();
        if ($updater) {
            $updater($source, $domain);
        } else {
            $method = $handler->getSourceMethod('update');
            $this->$method($source, $domain);
        }

        return $source;
    }

    protected function addSource(Handler $handler, $domain) : void
    {
        $mapper = $handler->getMapperClass();
        $method = $handler->getSourceMethod('new');
        $source = $this->atlas->$method($mapper);
        $this->storage->attach($domain, $source);
        $this->refresh->attach($domain);
    }

    protected function updateRecord(Record $record, $domain) : void
    {
        $handler = $this->getHandler($domain);
        $values = $this->updateRecordValues($record, $handler, $domain);
        $fields = array_merge(
            array_keys($record->getRow()->getArrayCopy()),
            array_keys($record->getRelated()->getFields())
        );

        foreach ($fields as $field) {

            $name = $handler->getRecordFromDomain($field);
            if ($name === null) {
                $name = $this->sourceCase->convert($field, $this->domainCase);
            }

            if (array_key_exists($name, $values)) {
                $record->$field = $values[$name];
            }
        }
    }

    protected function updateRecordValues(
        Record $record,
        Entity $handler,
        $domain
    ) : array
    {
        $values = [];
        $method = $handler->getDomainMethod('update') . 'RecordValue';
        $properties = $handler->getProperties();
        foreach ($properties as $name => $property) {
            $values[$name] = $this->$method($handler, $property, $domain, $record);
        }

        return $values;
    }

    protected function updateEntityRecordValue(
        Entity $handler,
        $property,
        $domain,
        Record $record
    ) {
        $value = $property->getValue($domain);

        $hasHandler = is_object($value) && isset($this->handlers[get_class($value)]);
        if ($hasHandler) {
            return $this->updateSource($value);
        }

        return $value;
    }

    protected function updateAggregateRecordValue(
        Aggregate $handler,
        $property,
        $domain,
        Record $record
    ) {
        $value = $property->getValue($domain);
        if ($handler->isRoot($value)) {
            return $this->updateRecord($record, $value);
        }

        return $this->updateEntityRecordValue($handler, $property, $domain, $record);
    }

    protected function updateRecordSet(RecordSet $recordSet, $domain) : void
    {
        $priorRecords = new SplObjectStorage();
        foreach ($recordSet->removeAll() as $record) {
            $priorRecords->attach($record);
        }

        foreach ($domain as $member) {
            $record = $this->updateSource($member);
            $recordSet[] = $record;
            if ($priorRecords->contains($record)) {
                $priorRecords->detach($record);
            }
        }

        /*
        the problem here is that the record might be part of
        *another* Domain collection, from which it *should not*
        be removed:

        foreach ($priorRecords as $priorRecord) {
            $priorRecord->markForDeletion();
        }

        so how can we automatically remove records that should be removed?

        further, how can we track association-mapping tables automatically?
        of course, we have the Record and the Mapper and the Relationships,
        so we should be able to inspect those to add/remove "through" values
        as needed.
        */
    }

    protected function deleteSource($domain)
    {
        if (! $this->storage->contains($domain)) {
            throw new Exception("no source for domain");
        }

        $source = $this->storage[$domain];
        $source->markForDeletion();
        return $source;
    }

    // PLAN TO insert/update
    public function store($domain)
    {
        if ($this->plan->contains($domain)) {
            $this->plan->detach($domain);
        }
        $this->plan->attach($domain, 'updateSource');
    }

    // PLAN TO delete
    public function discard($domain)
    {
        if ($this->plan->contains($domain)) {
            $this->plan->detach($domain);
        }
        $this->plan->attach($domain, 'deleteSource');
    }

    public function persist()
    {
        $this->transaction = $this->atlas->newTransaction();

        foreach ($this->plan as $domain) {
            $method = $this->plan->getInfo();
            $source = $this->$method($domain);
            $this->persistInTransaction($source);
        }

        $result = $this->transaction->exec();
        if ($result === false) {
            return false;
        }

        foreach ($this->refresh as $domain) {
            $source = $this->storage[$domain];
            $this->refresh($domain, $source);
            $this->refresh->detach($domain);
        }

        // and: how to associate records, esp. failed records, with
        // domain objects? or do we care about the domain objects at
        // this point?

        // reset the plan
        $this->plan = new SplObjectStorage();
        return true;
    }

    protected function persistInTransaction($source)
    {
        if ($source instanceof RecordSet) {
            foreach ($source as $record) {
                $this->persistInTransaction($record);
            }
            return;
        }

        $this->transaction->persist($source);
    }

    // refresh "new" domain objects with "new" record autoinc values, if any
    protected function refresh($domain, $source)
    {
        $handler = $this->getHandler($domain);
        $method = $handler->getDomainMethod('refresh');
        $this->$method($handler, $domain, $source);
    }

    protected function refreshAggregate(
        Aggregate $handler,
        $domain,
        Record $record
    ) {
        $properties = $handler->getProperties();
        foreach ($properties as $name => $prop) {
            $this->refreshAggregateProperty($prop, $handler, $domain, $record);
        }
    }

    protected function refreshAggregateProperty(
        ReflectionProperty $prop,
        Aggregate $handler,
        $domain,
        Record $record
    ) {
        $propValue = $prop->getValue($domain);
        $propType = gettype($propValue);
        if (is_object($propValue)) {
            $propType = get_class($propValue);
        }

        // if the property is a Root, process it with the Record itself
        if ($handler->isRoot($propType)) {
            $this->refresh($propValue, $record);
            return;
        }

        $this->refreshEntityProperty($prop, $handler, $domain, $record);
    }

    protected function refreshEntity(Entity $handler, $domain, Record $record)
    {
        $properties = $handler->getProperties();
        foreach ($properties as $name => $prop) {
            $this->refreshEntityProperty($prop, $handler, $domain, $record);
        }
    }

    protected function refreshEntityProperty(
        ReflectionProperty $prop,
        Entity $handler,
        $domain,
        Record $record
    ) {
        // get this (possibly custom) record field for the domain property
        $name = $prop->getName();
        $custom = $handler->getDomainFromRecord($name);
        if (is_string($custom)) {
            $field = $custom;
        } else {
            $field = $this->domainCase->convert($name, $this->sourceCase);
        }
        // WHAT IF IT'S A CLOSURE?

        $propValue = $prop->getValue($domain);

        $propType = gettype($propValue);
        if (is_object($propValue)) {
            $propType = get_class($propValue);
        }

        // is the property a type handled by Transit?
        if (isset($this->handlers[$propType])) {
            $this->refresh($propValue, $record->$field);
            return;
        }

        // is the field the same as the autoinc field?
        $autoincField = $this
            ->atlas
            ->mapper($handler->getMapperClass())
            ->getTable()
            ->getAutoinc();

        if ($field === $autoincField) {
            $autoincValue = $record->$field;
            settype($autoincValue, $propType);
            $prop->setValue($domain, $autoincValue);
        }
    }

    protected function refreshCollection(
        Collection $handler,
        $domain,
        RecordSet $recordSet
    ) {
        foreach ($domain as $member) {
            $source = $this->storage[$member];
            $this->refresh($member, $source);
        }
    }
}

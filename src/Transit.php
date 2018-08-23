<?php
namespace Atlas\Transit;

use ArrayObject;
use Atlas\Orm\Atlas;
use Atlas\Mapper\Record;
use Atlas\Mapper\RecordSet;
use Atlas\Transit\CaseConverter\CaseConverter;
use Atlas\Transit\CaseConverter\SnakeCase;
use Atlas\Transit\CaseConverter\CamelCase;
use Atlas\Transit\Handler\AggregateHandler;
use Atlas\Transit\Handler\CollectionHandler;
use Atlas\Transit\Handler\EntityHandler;
use Atlas\Transit\Handler\Handler;
use Closure;
use ReflectionParameter;
use ReflectionProperty;
use SplObjectStorage;

class Transit
{
    protected $sourceNamespace;

    protected $entityNamespace;

    protected $entityNamespaceLen;

    protected $aggregateNamespace;

    protected $aggregateNamespaceLen;

    protected $handlers = [];

    protected $storage;

    protected $refresh;

    protected $caseConverter;

    protected $atlas;

    protected $plan;

    public function __construct(
        Atlas $atlas,
        string $sourceNamespace,
        string $domainNamespace,
        CaseConverter $caseConverter = null
    ) {
        if ($caseConverter === null) {
            $caseConverter = new CaseConverter(
                new SnakeCase(),
                new CamelCase()
            );
        }

        $this->atlas = $atlas;
        $this->sourceNamespace = rtrim($sourceNamespace, '\\') . '\\';
        $this->entityNamespace = rtrim($domainNamespace, '\\') . '\\Entity\\';
        $this->entityNamespaceLen = strlen($this->entityNamespace);
        $this->aggregateNamespace = rtrim($domainNamespace, '\\') . '\\Aggregate\\';
        $this->aggregateNamespaceLen = strlen($this->aggregateNamespace);
        $this->caseConverter = $caseConverter;
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

    protected function getHandler($domainClass) : ?Handler
    {
        if (is_object($domainClass)) {
            $domainClass = get_class($domainClass);
        }

        if (! class_exists($domainClass)) {
            throw new Exception("Domain class '{$domainClass}' does not exist.");
        }

        if (! array_key_exists($domainClass, $this->handlers)) {
            $this->handlers[$domainClass] = $this->newHandler($domainClass);
        }

        return $this->handlers[$domainClass];
    }

    protected function newHandler(string $domainClass) : ?Handler
    {
        $isEntity = $this->entityNamespace == substr(
            $domainClass, 0, $this->entityNamespaceLen
        );

        if ($isEntity) {
            $class = $this->sourceNamespace . substr(
                $domainClass, $this->entityNamespaceLen
            );
            $parts = explode('\\', $class);
            array_pop($parts);
            $final = end($parts);

            $handlerClass = EntityHandler::CLASS;
            if (substr($domainClass, -10) == 'Collection') {
                $handlerClass = CollectionHandler::CLASS;
                // $final = substr($final, 0, -10);
            }

            $mapperClass = implode('\\', $parts) . '\\' . $final;
            return new $handlerClass($mapperClass, $domainClass);
        }

        $isAggregate = $this->aggregateNamespace == substr(
            $domainClass, 0, $this->aggregateNamespaceLen
        );

        if ($isAggregate) {
            $class = $this->sourceNamespace . substr(
                $domainClass, $this->aggregateNamespaceLen
            );
            $parts = explode('\\', $class);
            array_pop($parts);
            $final = end($parts);
            $mapperClass = implode('\\', $parts) . '\\' . $final;
            return new AggregateHandler($mapperClass, $domainClass);
        }

        return null;
    }

    public function new(string $domainClass, $source = null)
    {
        $handler = $this->getHandler($domainClass);
        if ($handler === null) {
            return new $domainClass($source);
        }
        $method = $handler->getDomainMethod('new');
        $domain = $this->$method($handler, $source);
        $this->storage->attach($domain, $source);
        return $domain;
    }

    protected function newEntity(EntityHandler $handler, Record $record)
    {
        $values = [];

        // now, this is a little tricky. the DC will receive *record* values
        // under the *domain* property names. should the DC receive them
        // first, under their *record* names?
        foreach ($record as $field => $value) {
            $name = $this->caseConverter->fromRecordToDomain($field);
            $values[$name] = $value;
        }

        $args = [];
        foreach ($handler->getParameters() as $param) {
            $args[] = $this->newEntityValue($param, $values);
        }

        $domainClass = $handler->getDomainClass();
        return new $domainClass(...$args);
    }

    protected function newAggregate(AggregateHandler $handler, Record $record)
    {
        $values = [];
        foreach ($handler->getParameters() as $param) {
            $values[] = $this->getAggregateValue($param, $handler, $record);
        }

        $domainClass = $handler->getDomainClass();
        return new $domainClass(...$values);
    }

    protected function newCollection(CollectionHandler $handler, RecordSet $recordSet)
    {
        $members = [];
        foreach ($recordSet as $record) {
            $memberClass = $handler->getMemberClass($record);
            $members[] = $this->new($memberClass, $record);
        }

        $domainClass = $handler->getDomainClass();
        return new $domainClass($members);
    }

    protected function newEntityValue(
        ReflectionParameter $param,
        array $values
    ) {
        $name = $param->getName();

        if (! array_key_exists($name, $values)) {
            return $param->isDefaultValueAvailable()
                ? $param->getDefaultValue()
                : null;
        }

        $value = $values[$name];

        $class = $param->getClass();

        // value object => matching class: leave as is
        if (is_object($value) && $value instanceof $class) {
            return $value;
        }

        // any value => a class: presume a domain object
        if ($class !== null) {
            return $this->new($class->getName(), $value);
        }

        // any value => non-class: cast to scalar type
        // @todo: allow for nullable types
        $type = $param->getType();
        if ($type !== null) {
            settype($value, $type);
        }

        return $value;
    }

    protected function getAggregateValue(
        ReflectionParameter $param,
        AggregateHandler $handler,
        Record $record
    ) {
        if ($handler->isRoot($param)) {
            return $this->new($param->getClass()->getName(), $record);
        }

        return $this->newEntityValue($param, $handler, $record);
    }

    protected function updateSource($domain)
    {
        $handler = $this->getHandler($domain);

        if (! $this->storage->contains($domain)) {
            $this->addSource($handler, $domain);
        }

        $source = $this->storage[$domain];
        $method = $handler->getSourceMethod('update');
        $this->$method($source, $domain);

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
        $values = $this->getRecordValues($record, $handler, $domain);
        /* @todo DataConverter */
        foreach ($values as $field => $value) {
            if ($record->has($field)) {
                $record->$field = $value;
            }
        }
    }

    protected function getRecordValues(
        Record $record,
        EntityHandler $handler,
        $domain
    ) : array
    {
        $values = [];
        $method = $handler->getDomainMethod('get') . 'RecordValue';
        $properties = $handler->getProperties();
        foreach ($properties as $name => $property) {
            $field = $this->caseConverter->fromDomainToRecord($name);
            $values[$field] = $this->$method($handler, $property, $domain, $record);
        }
        return $values;
    }

    protected function getEntityRecordValue(
        EntityHandler $handler,
        $property,
        $domain,
        Record $record
    ) {
        $value = $property->getValue($domain);
        if (! is_object($value)) {
            return $value;
        }

        $handler = $this->getHandler($value);
        if ($handler !== null) {
            return $this->updateSource($value);
        }

        return $value;
    }

    protected function getAggregateRecordValue(
        AggregateHandler $handler,
        $property,
        $domain,
        Record $record
    ) {
        $value = $property->getValue($domain);
        if ($handler->isRoot($value)) {
            return $this->updateRecord($record, $value);
        }

        return $this->getEntityRecordValue($handler, $property, $domain, $record);
    }

    protected function updateRecordSet(RecordSet $recordSet, $domain) : void
    {
        $priorRecords = new SplObjectStorage();
        foreach ($recordSet->detachAll() as $record) {
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
        foreach ($this->plan as $domain) {
            $method = $this->plan->getInfo();
            $source = $this->$method($domain);
            $this->_persist($source);
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
    }

    protected function _persist($source) : void
    {
        if ($source instanceof RecordSet) {
            $this->atlas->persistRecordSet($source);
        } else {
            $this->atlas->persist($source);
        }
    }

    // refresh "new" domain objects with "new" record autoinc values, if any
    protected function refresh($domain, $source)
    {
        $handler = $this->getHandler($domain);
        $method = $handler->getDomainMethod('refresh');
        $this->$method($handler, $domain, $source);
    }

    protected function refreshAggregate(
        AggregateHandler $handler,
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
        AggregateHandler $handler,
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

    protected function refreshEntity(EntityHandler $handler, $domain, Record $record)
    {
        $properties = $handler->getProperties();
        foreach ($properties as $name => $prop) {
            $this->refreshEntityProperty($prop, $handler, $domain, $record);
        }
    }

    protected function refreshEntityProperty(
        ReflectionProperty $prop,
        EntityHandler $handler,
        $domain,
        Record $record
    ) {
        $name = $prop->getName();
        $field = $this->caseConverter->fromDomainToRecord($name);

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
        CollectionHandler $handler,
        $collection,
        RecordSet $recordSet
    ) {
        foreach ($collection as $member) {
            $source = $this->storage[$member];
            $this->refresh($member, $source);
        }
    }
}

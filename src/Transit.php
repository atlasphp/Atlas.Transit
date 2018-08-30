<?php
namespace Atlas\Transit;

use ArrayObject;
use Atlas\Orm\Atlas;
use Atlas\Mapper\Record;
use Atlas\Mapper\RecordSet;
use Atlas\Transit\CaseConverter;
use Atlas\Transit\Casing\SnakeCase;
use Atlas\Transit\Casing\CamelCase;
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

    public function new(string $domainClass, $source = null)
    {
        $handler = $this->getHandler($domainClass);
        $method = $handler->getDomainMethod('new');
        $domain = $this->$method($handler, $source);
        $this->storage->attach($domain, $source);
        return $domain;
    }

    protected function newEntity(EntityHandler $handler, Record $record)
    {
        // passes 1 & 2: data from record, after custom conversions
        $values = $this->convertFromRecord($record, $handler);

        // pass 3: set types and create other domain objects as needed
        $args = [];
        foreach ($handler->getParameters() as $name => $param) {
            $args[] = $this->newEntityValue($param, $values[$name]);
        }

        // done
        return $handler->new($args);
    }

    protected function newEntityValue(
        ReflectionParameter $param,
        $value
    ) {
        $class = $param->getClass();

        if ($class === null) {
            // any value => non-class: cast to scalar type
            // @todo: allow for nullable types
            $type = $param->getType();
            if ($type !== null) {
                settype($value, $type);
            }
            return $value;
        }

        // value object => matching class: leave as is
        $type = $class->getName();
        if ($value instanceof $type) {
            return $value;
        }

        // any value => a class: presume a domain object
        return $this->new($type, $value);
    }

    protected function newCollection(
        CollectionHandler $handler,
        RecordSet $recordSet
    ) {
        $members = [];
        foreach ($recordSet as $record) {
            $memberClass = $handler->getMemberClass($record);
            $members[] = $this->new($memberClass, $record);
        }

        return $handler->new($members);
    }

    protected function newAggregate(AggregateHandler $handler, Record $record)
    {
        // passes 1 & 2: data from record, after custom conversions
        $values = $this->convertFromRecord($record, $handler);

        // pass 3: set types and create other domain objects as needed
        $args = [];
        foreach ($handler->getParameters() as $name => $param) {
            $args[] = $this->newAggregateValue($param, $handler, $record, $values);
        }

        // done
        return $handler->new($args);
    }

    protected function newAggregateValue(
        ReflectionParameter $param,
        AggregateHandler $handler,
        Record $record,
        array $values
    ) {
        $name = $param->getName();
        $class = $param->getClass()->getName();

        // already an instance of the typehinted class?
        if ($values[$name] instanceof $class) {
            return $values[$name];
        }

        // for the Root Entity, create using the entire record
        if ($handler->isRoot($param)) {
            return $this->new($class, $record);
        }

        // for everything else, send only the matching value
        return $this->new($class, $values[$name]);
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

        $values = [];
        $method = $handler->getDomainMethod('get') . 'RecordValue';
        foreach ($handler->getProperties() as $name => $property) {
            $values[$name] = $this->$method(
                $handler,
                $property,
                $domain,
                $record
            );
        }

        $handler->getConverter()->fromDomainToRecord($values, $record);

        foreach ($values as $name => $value) {
            $field = $this->caseConverter->fromDomainToRecord($name);
            if ($record->has($field)) {
                $record->$field = $value;
            }
        }
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

        return $this->getEntityRecordValue(
            $handler,
            $property,
            $domain,
            $record
        );
    }

    protected function updateRecordSet(RecordSet $recordSet, $domain) : void
    {
        $recordSet->detachAll();
        foreach ($domain as $member) {
            $record = $this->updateSource($member);
            $recordSet[] = $record;
        }
    }

    protected function deleteSource($domain)
    {
        if (! $this->storage->contains($domain)) {
            throw new Exception("no source for domain");
        }

        $source = $this->storage[$domain];
        $source->setDelete();
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

        // unset/detach deleted as we go

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

    protected function refreshEntity(
        EntityHandler $handler,
        $domain,
        Record $record
    ) {
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
            ->getTable()::AUTOINC_COLUMN;

        if ($field === $autoincField) {
            $autoincValue = $record->$field;
            $prop->setValue($domain, (int) $autoincValue);
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

    protected function convertFromRecord($record, $handler) : array
    {
        $values = [];

        foreach ($handler->getParameters() as $name => $param) {
            $field = $this->caseConverter->fromDomainToRecord($name);
            if ($record->has($field)) {
                $values[$name] = $record->$field;
            } elseif ($param->isDefaultValueAvailable()) {
                $values[$name] = $param->getDefaultValue();
            } else {
                $values[$name] = null;
            }
        }

        $handler->getConverter()->fromRecordToDomain($record, $values);

        return $values;
    }

}

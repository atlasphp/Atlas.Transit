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
use Atlas\Transit\Handler\HandlerFactory;
use Closure;
use ReflectionParameter;
use ReflectionProperty;
use SplObjectStorage;

/**
 *
 * Toward a standard vocabulary:
 *
 * We think most broadly in terms of the domain (aggregate, entity, collection,
 * value object) and the source (mapper, record, recordset).
 *
 * Domain objects have properties, parameters, and arguments; source objects
 * have fields. Or perhaps we talk in terms of "elements" ?
 *
 * Want to keep away from the word "value" because it can be conflated with
 * Value Object; use $data for arrays and $datum for elements.
 *
 * ---
 *
 * Also want to standardize on order of parameters: domain first, or source
 * first?
 *
 */
class Transit
{
    protected $handlers = [];

    protected $storage;

    protected $refresh;

    protected $caseConverter;

    protected $handlerFactory;

    protected $atlas;

    protected $plan;

    public static function new(
        Atlas $atlas,
        string $sourceNamespace,
        string $domainNamespace,
        string $sourceCasingClass = SnakeCase::CLASS,
        string $domainCasingClass = CamelCase::CLASS
    ) {
        return new Transit(
            $atlas,
            new HandlerFactory($sourceNamespace, $domainNamespace),
            new CaseConverter(
                new $sourceCasingClass(),
                new $domainCasingClass()
            )
        );
    }

    public function __construct(
        Atlas $atlas,
        HandlerFactory $handlerFactory,
        CaseConverter $caseConverter
    ) {
        $this->atlas = $atlas;
        $this->handlerFactory = $handlerFactory;
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
            $this->handlers[$domainClass] = $this->handlerFactory->new($domainClass);
        }

        return $this->handlers[$domainClass];
    }

    public function newDomain(string $domainClass, $source = null)
    {
        $handler = $this->getHandler($domainClass);
        $method = $handler->getDomainMethod('newDomain');
        $domain = $this->$method($handler, $source);
        $this->storage->attach($domain, $source);
        return $domain;
    }

    protected function newDomainEntity(EntityHandler $handler, Record $record)
    {
        // passes 1 & 2: data from record, after custom conversions
        $values = $this->convertFromRecord($record, $handler);

        // pass 3: set types and create other domain objects as needed
        $args = [];
        foreach ($handler->getParameters() as $name => $param) {
            $args[] = $this->newDomainEntityArgument($param, $values[$name]);
        }

        // done
        return $handler->new($args);
    }

    protected function newDomainEntityArgument(
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
        return $this->newDomain($type, $value);
    }

    protected function newDomainCollection(
        CollectionHandler $handler,
        RecordSet $recordSet
    ) {
        $members = [];
        foreach ($recordSet as $record) {
            $memberClass = $handler->getMemberClass($record);
            $members[] = $this->newDomain($memberClass, $record);
        }

        return $handler->new($members);
    }

    protected function newDomainAggregate(AggregateHandler $handler, Record $record)
    {
        // passes 1 & 2: data from record, after custom conversions
        $values = $this->convertFromRecord($record, $handler);

        // pass 3: set types and create other domain objects as needed
        $args = [];
        foreach ($handler->getParameters() as $name => $param) {
            $args[] = $this->newDomainAggregateArgument($param, $handler, $record, $values);
        }

        // done
        return $handler->new($args);
    }

    protected function newDomainAggregateArgument(
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
            return $this->newDomain($class, $record);
        }

        // for everything else, send only the matching value
        return $this->newDomain($class, $values[$name]);
    }

    protected function updateSource($domain)
    {
        $handler = $this->getHandler($domain);

        if (! $this->storage->contains($domain)) {
            $this->addSource($handler, $domain);
        }

        $source = $this->storage[$domain];
        $method = $handler->getSourceMethod('updateSource');
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

    protected function updateSourceRecord(Record $record, $domain) : void
    {
        $handler = $this->getHandler($domain);

        $values = [];
        $method = $handler->getDomainMethod('update') . 'SourceRecordValue';
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

    protected function updateEntitySourceRecordValue(
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

    protected function updateAggregateSourceRecordValue(
        AggregateHandler $handler,
        $property,
        $domain,
        Record $record
    ) {
        $value = $property->getValue($domain);
        if ($handler->isRoot($value)) {
            return $this->updateSourceRecord($record, $value);
        }

        return $this->updateEntitySourceRecordValue(
            $handler,
            $property,
            $domain,
            $record
        );
    }

    protected function updateSourceRecordSet(RecordSet $recordSet, $domain) : void
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
    public function store($domain) : void
    {
        if ($this->plan->contains($domain)) {
            $this->plan->detach($domain);
        }
        $this->plan->attach($domain, 'updateSource');
    }

    // PLAN TO delete
    public function discard($domain) : void
    {
        if ($this->plan->contains($domain)) {
            $this->plan->detach($domain);
        }
        $this->plan->attach($domain, 'deleteSource');
    }

    public function persist() : void
    {
        foreach ($this->plan as $domain) {
            $method = $this->plan->getInfo();
            $source = $this->$method($domain);
            $this->_persist($source);
        }

        foreach ($this->refresh as $domain) {
            $source = $this->storage[$domain];
            $this->refreshDomain($domain, $source);
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
    protected function refreshDomain($domain, $source) : void
    {
        $handler = $this->getHandler($domain);
        $method = $handler->getDomainMethod('refreshDomain');
        $this->$method($handler, $domain, $source);
    }

    protected function refreshDomainAggregate(
        AggregateHandler $handler,
        $domain,
        Record $record
    ) : void
    {
        $properties = $handler->getProperties();
        foreach ($properties as $name => $prop) {
            $this->refreshDomainAggregateProperty($prop, $handler, $domain, $record);
        }
    }

    protected function refreshDomainAggregateProperty(
        ReflectionProperty $prop,
        AggregateHandler $handler,
        $domain,
        Record $record
    ) : void
    {
        $propValue = $prop->getValue($domain);
        $propType = gettype($propValue);
        if (is_object($propValue)) {
            $propType = get_class($propValue);
        }

        // if the property is a Root, process it with the Record itself
        if ($handler->isRoot($propType)) {
            $this->refreshDomain($propValue, $record);
            return;
        }

        $this->refreshDomainEntityProperty($prop, $handler, $domain, $record);
    }

    protected function refreshDomainEntity(
        EntityHandler $handler,
        $domain,
        Record $record
    ) : void
    {
        $properties = $handler->getProperties();
        foreach ($properties as $name => $prop) {
            $this->refreshDomainEntityProperty($prop, $handler, $domain, $record);
        }
    }

    protected function refreshDomainEntityProperty(
        ReflectionProperty $prop,
        EntityHandler $handler,
        $domain,
        Record $record
    ) : void
    {
        $propValue = $prop->getValue($domain);

        $propType = gettype($propValue);
        if (is_object($propValue)) {
            $propType = get_class($propValue);
        }

        $name = $prop->getName();
        $field = $this->caseConverter->fromDomainToRecord($name);

        // is the property a type handled by Transit?
        if (isset($this->handlers[$propType])) {
            $this->refreshDomain($propValue, $record->$field);
            return;
        }

        // is the field the same as the autoinc field?
        if ($field === $handler->getAutoincColumn()) {
            $autoincValue = $record->$field;
            $prop->setValue($domain, (int) $autoincValue);
        }
    }

    protected function refreshDomainCollection(
        CollectionHandler $handler,
        $collection,
        RecordSet $recordSet
    ) : void
    {
        foreach ($collection as $member) {
            $source = $this->storage[$member];
            $this->refreshDomain($member, $source);
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

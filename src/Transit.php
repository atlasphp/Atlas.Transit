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
 * Also want to standardize on parameter precedence:
 *
 * handler, param/property, domain/domainClass, record, data/datum
 *
 * @todo getAtlas()
 *
 * @todo Have TransitSelect extend MapperSelect, and configure Atlas to
 * factory *that* instead of MapperSelect? Would provide "transparent"
 * access to all select methods. Maybe leave select($whereEquals) and make
 * fetchDomain($domainClass) -- no, need to know the $domainClass early to
 * figure which MapperSelect to use.
 *
 * @todo Consider persist/delete/flush instead of store/discard/persist.
 *
 * @todo Expose Atlas via __call() ? Would affect the store/flush/etc. naming.
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
        if ($handler === null) {
            throw new Exception("No handler for class '$domainClass'.");
        }
        return $this->_newDomain($handler, $source);
    }

    protected function _newDomain(Handler $handler, $source)
    {
        $method = $handler->getDomainMethod('newDomain');
        $domain = $this->$method($handler, $source);
        $this->storage->attach($domain, $source);
        return $domain;
    }

    protected function newDomainEntity(EntityHandler $handler, Record $record)
    {
        // passes 1 & 2: data from record, after custom conversions
        $data = $this->convertSourceData($handler, $record);

        // pass 3: set types and create other domain objects as needed
        $args = [];
        foreach ($handler->getParameters() as $name => $param) {
            $args[] = $this->newDomainEntityArgument($handler, $param, $data[$name]);
        }

        // done
        return $handler->new($args);
    }

    protected function newDomainEntityArgument(
        EntityHandler $handler,
        ReflectionParameter $param,
        $datum
    ) {
        if ($param->allowsNull() && $datum === null) {
            return $datum;
        }

        $type = $handler->getType($param->getName());
        if ($type !== null) {
            settype($datum, $type);
            return $datum;
        }

        $class = $param->getClass();
        if ($class === null) {
            return $datum;
        }

        // value object => matching class: leave as is
        $type = $class->getName();
        if ($datum instanceof $type) {
            return $datum;
        }

        // any value => a class
        $subhandler = $this->getHandler($type);
        if ($subhandler !== null) {
            // use handler for domain object
            return $this->_newDomain($subhandler, $datum);
        }

        // @todo report the domain class and what converter was being used
        throw new Exception("No handler for \$" . $param->getName() . " typehint of {$type}.");
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
        $data = $this->convertSourceData($handler, $record);

        // pass 3: set types and create other domain objects as needed
        $args = [];
        foreach ($handler->getParameters() as $name => $param) {
            $args[] = $this->newDomainAggregateArgument($handler, $param, $record, $data);
        }

        // done
        return $handler->new($args);
    }

    protected function newDomainAggregateArgument(
        AggregateHandler $handler,
        ReflectionParameter $param,
        Record $record,
        array $data
    ) {
        $name = $param->getName();
        $class = $param->getClass()->getName();

        // already an instance of the typehinted class?
        if ($data[$name] instanceof $class) {
            return $data[$name];
        }

        // for the Root Entity, create using the entire record
        if ($handler->isRoot($param)) {
            return $this->newDomain($class, $record);
        }

        // for everything else, send only the matching value
        return $this->newDomain($class, $data[$name]);
    }

    protected function convertSourceData(Handler $handler, Record $record) : array
    {
        $data = [];

        // pass 1: data directly from source
        foreach ($handler->getParameters() as $name => $param) {
            $field = $this->caseConverter->fromDomainToSource($name);
            if ($record->has($field)) {
                $data[$name] = $record->$field;
            } elseif ($param->isDefaultValueAvailable()) {
                $data[$name] = $param->getDefaultValue();
            } else {
                $data[$name] = null;
            }
        }

        // pass 2: convert source data to domain
        $handler->getDataConverter()->fromSourceToDomain($record, $data);

        return $data;
    }

    protected function updateSource($domain)
    {
        $handler = $this->getHandler($domain);

        if (! $this->storage->contains($domain)) {
            $mapper = $handler->getMapperClass();
            $method = $handler->getSourceMethod('new');
            $source = $this->atlas->$method($mapper);
            $this->storage->attach($domain, $source);
            $this->refresh->attach($domain);
        }

        $source = $this->storage[$domain];
        $method = $handler->getSourceMethod('updateSource');
        $this->$method($domain, $source);

        return $source;
    }

    protected function updateSourceRecord($domain, Record $record) : void
    {
        $handler = $this->getHandler($domain);

        $data = [];
        $method = 'updateSourceRecord' . $handler->getDomainMethod('From');
        foreach ($handler->getProperties() as $name => $property) {
            $data[$name] = $this->$method(
                $handler,
                $domain,
                $record,
                $property->getValue($domain)
            );
        }

        $handler->getDataConverter()->fromDomainToSource($data, $record);

        foreach ($data as $name => $datum) {
            $field = $this->caseConverter->fromDomainToSource($name);
            if ($record->has($field)) {
                $record->$field = $datum;
            }
        }
    }

    protected function updateSourceRecordFromEntity(
        EntityHandler $handler,
        $domain,
        Record $record,
        $datum
    ) {
        if (! is_object($datum)) {
            return $datum;
        }

        $handler = $this->getHandler($datum);
        if ($handler !== null) {
            return $this->updateSource($datum);
        }

        return $datum;
    }

    protected function updateSourceRecordFromAggregate(
        AggregateHandler $handler,
        $domain,
        Record $record,
        $datum
    ) {
        if ($handler->isRoot($datum)) {
            return $this->updateSourceRecord($datum, $record);
        }

        return $this->updateSourceRecordFromEntity(
            $handler,
            $domain,
            $record,
            $datum
        );
    }

    protected function updateSourceRecordSet($domain, RecordSet $recordSet) : void
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
            $this->refreshDomainAggregateProperty($handler, $prop, $domain, $record);
        }
    }

    protected function refreshDomainAggregateProperty(
        AggregateHandler $handler,
        ReflectionProperty $prop,
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

        $this->refreshDomainEntityProperty($handler, $prop, $domain, $record);
    }

    protected function refreshDomainEntity(
        EntityHandler $handler,
        $domain,
        Record $record
    ) : void
    {
        $properties = $handler->getProperties();
        foreach ($properties as $name => $prop) {
            $this->refreshDomainEntityProperty($handler, $prop, $domain, $record);
        }
    }

    protected function refreshDomainEntityProperty(
        EntityHandler $handler,
        ReflectionProperty $prop,
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
        $field = $this->caseConverter->fromDomainToSource($name);

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
}

<?php
declare(strict_types=1);

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

    public $caseConverter;

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

    public function select(string $domainClass, array $whereEquals = []) : TransitSelect
    {
        $handler = $this->getHandler($domainClass);

        return new TransitSelect(
            $this,
            $this->atlas->select($handler->getMapperClass(), $whereEquals),
            $handler->getSourceMethod('fetch'),
            $domainClass
        );
    }

    public function getHandler($domainClass) : ?Handler
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

    public function _newDomain(Handler $handler, $source)
    {
        $method = $handler->getDomainMethod('newDomain');
        $domain = $this->$method($handler, $source);
        $this->storage->attach($domain, $source);
        return $domain;
    }

    protected function newDomainEntity(EntityHandler $handler, Record $record)
    {
        return $handler->newDomain($this, $record);
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
        return $handler->newDomain($this, $record);
    }

    public function updateSource($domain)
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
        $handler->updateSource($this, $domain, $record);
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
        $name = $prop->getName();
        $field = $this->caseConverter->fromDomainToSource($name);

        if ($handler->isAutoincColumn($field)) {
            $type = $handler->getType($name);
            $datum = $record->$field;
            if ($type !== null && $datum !== null) {
                settype($datum, $type);
            }
            $prop->setValue($domain, $datum);
            return;
        }

        $datum = $prop->getValue($domain);
        if (! is_object($datum)) {
            return;
        }

        // is the property a type handled by Transit?
        $class = get_class($datum);
        $subhandler = $this->getHandler($class);
        if ($subhandler !== null) {
            // because there may be domain objects not created through Transit
            $this->refreshDomain($datum, $record->$field);
            return;
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

<?php
declare(strict_types=1);

namespace Atlas\Transit;

use Atlas\Mapper\RecordSet;
use Atlas\Orm\Atlas;
use Atlas\Transit\Casing\CamelCase;
use Atlas\Transit\Casing\SnakeCase;
use Atlas\Transit\Handler\HandlerLocator;
use Atlas\Transit\Handler\ValueObjectHandler;
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
 */
class Transit
{
    protected $atlas;

    protected $handlerLocator;

    protected $plan;

    public static function new(
        Atlas $atlas,
        string $sourceNamespace,
        string $sourceCasingClass = SnakeCase::CLASS,
        string $domainCasingClass = CamelCase::CLASS
    ) {
        $inflector =  new Inflector(
            new $sourceCasingClass(),
            new $domainCasingClass()
        );

        $reflections = new Reflections(
            $sourceNamespace,
            $inflector
        );

        return new static(
            $atlas,
            new HandlerLocator(
                $atlas,
                $reflections
            )
        );
    }

    public function __construct(
        Atlas $atlas,
        HandlerLocator $handlerLocator
    ) {
        $this->atlas = $atlas;
        $this->handlerLocator = $handlerLocator;
        $this->plan = new SplObjectStorage();
    }

    public function getAtlas() : Atlas
    {
        return $this->atlas;
    }

    public function select(string $domainClass, array $whereEquals = []) : TransitSelect
    {
        $handler = $this->handlerLocator->get($domainClass);

        return new TransitSelect(
            $this->atlas->select($handler->getMapperClass(), $whereEquals),
            $handler
        );
    }

    // PLAN TO insert/update
    public function store(object $domain) : void
    {
        if ($this->plan->contains($domain)) {
            $this->plan->detach($domain);
        }
        $this->plan->attach($domain, 'updateSource');
    }

    // PLAN TO delete
    public function discard(object $domain) : void
    {
        if ($this->plan->contains($domain)) {
            $this->plan->detach($domain);
        }
        $this->plan->attach($domain, 'deleteSource');
    }

    public function persist() : void
    {
        $refresh = new SplObjectStorage();

        foreach ($this->plan as $domain) {
            $handler = $this->handlerLocator->get($domain);
            $method = $this->plan->getInfo();
            $source = $handler->$method($domain, $refresh);
            if ($source instanceof RecordSet) {
                $this->atlas->persistRecordSet($source);
            } else {
                $this->atlas->persist($source);
            }
        }

        foreach ($refresh as $domain) {
            $handler = $this->handlerLocator->get($domain);
            $handler->refreshDomain($domain, $refresh);
        }

        // unset/detach deleted as we go

        // and: how to associate records, esp. failed records, with
        // domain objects? or do we care about the domain objects at
        // this point?

        // reset the plan
        $this->plan = new SplObjectStorage();
    }
}

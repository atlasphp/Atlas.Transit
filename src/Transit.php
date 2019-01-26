<?php
declare(strict_types=1);

namespace Atlas\Transit;

use ArrayObject;
use Atlas\Orm\Atlas;
use Atlas\Mapper\Record;
use Atlas\Mapper\RecordSet;
use Atlas\Transit\Inflector;
use Atlas\Transit\Casing\SnakeCase;
use Atlas\Transit\Casing\CamelCase;
use Atlas\Transit\Handler\AggregateHandler;
use Atlas\Transit\Handler\CollectionHandler;
use Atlas\Transit\Handler\EntityHandler;
use Atlas\Transit\Handler\Handler;
use Atlas\Transit\Handler\HandlerFactory;
use Atlas\Transit\Handler\HandlerLocator;
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
    protected $atlas;

    protected $handlerLocator;

    protected $plan;

    public static function new(
        Atlas $atlas,
        string $sourceNamespace,
        string $sourceCasingClass = SnakeCase::CLASS,
        string $domainCasingClass = CamelCase::CLASS
    ) {
        return new static(
            $atlas,
            new HandlerLocator(
                $atlas,
                $sourceNamespace,
                new Inflector(
                    new $sourceCasingClass(),
                    new $domainCasingClass()
                )
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

    public function select(string $domainClass, array $whereEquals = []) : TransitSelect
    {
        $handler = $this->handlerLocator->getOrThrow($domainClass);

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

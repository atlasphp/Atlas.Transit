<?php
declare(strict_types=1);

namespace Atlas\Transit;

use Atlas\Mapper\RecordSet;
use Atlas\Orm\Atlas;
use Atlas\Transit\Handler\HandlerLocator;
use Atlas\Transit\Inflector\Inflector;
use Atlas\Transit\Inflector\CamelCase;
use Atlas\Transit\Inflector\SnakeCase;
use Atlas\Transit\Reflection\ReflectionLocator;
use Closure;
use ReflectionParameter;
use ReflectionProperty;
use SplObjectStorage;

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
                new ReflectionLocator(
                    $sourceNamespace,
                    new Inflector(
                        new $sourceCasingClass(),
                        new $domainCasingClass()
                    )
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

    /**
     * Attach a domain object to plan.
     *
     * @param object $domain
     *
     * @return \Atlas\Transit\Transit
     * @throws \Atlas\Transit\Exception
     */
    public function attach(object $domain): Transit
    {
        $handler = $this->handlerLocator->get($domain);
        /** @var \Atlas\Mapper\Record $record */
        $record = $handler->updateSource($domain, $this->plan);
        $record->getRow()->init('');

        return $this;
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

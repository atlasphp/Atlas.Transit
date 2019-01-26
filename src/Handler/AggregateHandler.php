<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Mapper;
use Atlas\Mapper\Record;
use Atlas\Transit\Inflector;
use Atlas\Transit\Exception;
use Atlas\Transit\Transit;
use ReflectionParameter;
use ReflectionProperty;
use SplObjectStorage;

class AggregateHandler extends EntityHandler
{
    protected $rootClass;

    public function __construct(
        string $domainClass,
        Mapper $mapper,
        HandlerLocator $handlerLocator,
        SplObjectStorage $storage,
        Inflector $inflector,
        ValueObjectHandler $valueObjectHandler
    ) {
        parent::__construct(
            $domainClass,
            $mapper,
            $handlerLocator,
            $storage,
            $inflector,
            $valueObjectHandler
        );

        $this->rootClass = reset($this->parameters)->getClass()->getName();
    }

    public function isRoot(object $spec) : bool
    {
        if ($spec instanceof ReflectionParameter) {
            $class = $spec->getClass()->getName() ?? '';
            return $this->rootClass === $class;
        }

        return $this->rootClass === get_class($spec);
    }

    protected function newDomainArgument(
        ReflectionParameter $param,
        Record $record
    ) {
        $name = $param->getName();
        $class = $this->getClass($name);

        // for the Root Entity, create using the entire record
        if ($this->isRoot($param)) {
            $rootHandler = $this->handlerLocator->get($this->rootClass);
            return $rootHandler->newDomain($record);
        }

        // not the Root Entity, use normal creation
        return parent::newDomainArgument($param, $record);
    }

    protected function updateSourceField(
        Record $record,
        string $field,
        $datum,
        SplObjectStorage $refresh
    ) : void
    {
        if ($this->isRoot($datum)) {
            $handler = $this->handlerLocator->get($datum);
            $handler->updateSourceFields($datum, $record, $refresh);
            return;
        }

        parent::updateSourceField(
            $record,
            $field,
            $datum,
            $refresh
        );
    }

    protected function refreshDomainProperty(
        ReflectionProperty $prop,
        object $domain,
        $record,
        SplObjectStorage $refresh
    ) : void
    {
        $datum = $prop->getValue($domain);

        // if the property is a Root, process it with the Record itself
        if (is_object($datum) && $this->isRoot($datum)) {
            $handler = $this->handlerLocator->get($datum);
            $handler->refreshDomainProperties($datum, $record, $refresh);
            return;
        }

        parent::refreshDomainProperty($prop, $domain, $datum, $refresh);
    }
}

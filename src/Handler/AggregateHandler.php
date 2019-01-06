<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Record;
use Atlas\Transit\CaseConverter;
use Atlas\Transit\Exception;
use ReflectionParameter;
use ReflectionProperty;

class AggregateHandler extends EntityHandler
{
    protected $rootClass;

    public function __construct(
        string $domainClass,
        string $mapperClass,
        HandlerLocator $handlerLocator,
        CaseConverter $caseConverter
    ) {
        parent::__construct($domainClass, $mapperClass, $handlerLocator, $caseConverter);
        $this->rootClass = reset($this->parameters)->getClass()->getName();
    }

    public function isRoot($spec) : bool
    {
        if ($spec instanceof ReflectionParameter) {
            $class = $spec->getClass()->getName() ?? '';
            return $this->rootClass === $class;
        }

        if (is_object($spec)) {
            return $this->rootClass === get_class($spec);
        }

        return $this->rootClass === $spec;
    }

    protected function newDomainArgument(
        $transit,
        ReflectionParameter $param,
        Record $record
    ) {
        $name = $param->getName();
        $class = $this->getClass($name);

        // for the Root Entity, create using the entire record
        if ($this->isRoot($param)) {
            return $transit->newDomain($class, $record);
        }

        // not the Root Entity, use normal creation
        return parent::newDomainArgument($transit, $param, $record);
    }

    protected function updateSourceDatum(
        $transit,
        $domain,
        Record $record,
        $datum
    ) {
        if ($this->isRoot($datum)) {
            $handler = $this->handlerLocator->get(get_class($datum));
            return $handler->updateSource($transit, $datum, $record);
        }

        return parent::updateSourceDatum(
            $transit,
            $domain,
            $record,
            $datum
        );
    }

    protected function refreshDomainProperty(
        $transit,
        ReflectionProperty $prop,
        $domain,
        $record,
        $storage,
        $refresh
    ) : void
    {
        $propValue = $prop->getValue($domain);
        $propType = gettype($propValue);
        if (is_object($propValue)) {
            $propType = get_class($propValue);
        }

        // if the property is a Root, process it with the Record itself
        if ($this->isRoot($propType)) {
            $handler = $this->handlerLocator->get($propType);
            $handler->refreshDomain($transit, $propValue, $record, $storage, $refresh);
            return;
        }

        parent::refreshDomainProperty($transit, $prop, $domain, $propValue, $storage, $refresh);
    }
}

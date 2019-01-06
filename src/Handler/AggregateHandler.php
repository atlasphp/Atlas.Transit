<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Record;
use Atlas\Transit\CaseConverter;
use Atlas\Transit\Exception;
use Atlas\Transit\Transit;
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
        parent::__construct(
            $domainClass,
            $mapperClass,
            $handlerLocator,
            $caseConverter
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
        Transit $transit,
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
        Transit $transit,
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
        Transit $transit,
        ReflectionProperty $prop,
        $domain,
        $record,
        $storage,
        $refresh
    ) : void
    {
        $datum = $prop->getValue($domain);

        // if the property is a Root, process it with the Record itself
        if (is_object($datum) && $this->isRoot($datum)) {
            $handler = $this->handlerLocator->get(get_class($datum));
            $handler->refreshDomain($transit, $datum, $record, $storage, $refresh);
            return;
        }

        parent::refreshDomainProperty($transit, $prop, $domain, $datum, $storage, $refresh);
    }
}

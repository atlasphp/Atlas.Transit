<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Record;
use Atlas\Transit\Exception;
use ReflectionParameter;

class AggregateHandler extends EntityHandler
{
    protected $rootClass;

    public function __construct(string $domainClass, string $mapperClass)
    {
        parent::__construct($domainClass, $mapperClass);
        $this->rootClass = reset($this->parameters)->getClass()->getName();
    }

    public function getDomainMethod(string $method) : string
    {
        return $method . 'Aggregate';
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

    /**
     * @todo test this with value objects in the aggregate
     */
    public function newDomain($transit, $record)
    {
        $data = $this->convertSourceData($transit, $record);

        $args = [];
        foreach ($this->parameters as $name => $param) {
            $args[] = $this->newAggregateArgument($transit, $param, $record, $data);
        }

        // done
        return $this->new($args);
    }

    protected function newAggregateArgument(
        $transit,
        ReflectionParameter $param,
        Record $record,
        array $data
    ) {
        $name = $param->getName();
        $class = $this->getClass($name);

        // already an instance of the typehinted class?
        if ($data[$name] instanceof $class) {
            return $data[$name];
        }

        // for the Root Entity, create using the entire record
        if ($this->isRoot($param)) {
            return $transit->newDomain($class, $record);
        }

        // for everything else, send only the matching value
        return $transit->newDomain($class, $data[$name]);
    }

    protected function updateSourceDatum(
        $transit,
        $domain,
        Record $record,
        $datum
    ) {
        if ($this->isRoot($datum)) {
            $handler = $transit->getHandler($datum);
            return $handler->updateSource($transit, $datum, $record);
        }

        return parent::updateSourceDatum(
            $transit,
            $domain,
            $record,
            $datum
        );
    }
}

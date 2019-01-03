<?php
declare(strict_types=1);

namespace Atlas\Transit;

use Atlas\Mapper\Record;
use Atlas\Transit\Handler\Handler;
use Atlas\Transit\Handler\AggregateHandler;
use Atlas\Transit\Handler\EntityHandler;
use ReflectionParameter;

class DataConverter
{
    public function newDomainEntity($transit, EntityHandler $handler, Record $record)
    {
        // passes 1 & 2: data from record, after custom conversions
        $data = $this->convertSourceData($transit, $handler, $record);

        // pass 3: set types and create other domain objects as needed
        $args = [];
        foreach ($handler->getParameters() as $name => $param) {
            $args[] = $this->newDomainEntityArgument($transit, $handler, $param, $data[$name]);
        }

        // done
        return $handler->new($args);
    }

    protected function newDomainEntityArgument(
        $transit,
        EntityHandler $handler,
        ReflectionParameter $param,
        $datum
    ) {
        if ($param->allowsNull() && $datum === null) {
            return $datum;
        }

        $name = $param->getName();

        // non-class typehint?
        $type = $handler->getType($name);
        if ($type !== null) {
            settype($datum, $type);
            return $datum;
        }

        // class typehint?
        $class = $handler->getClass($name);
        if ($class === null) {
            return $datum;
        }

        // when you fetch with() a relationship, but there is no related,
        // Atlas Mapper returns `false`. as such, treat `false` like `null`
        // for class typehints.
        if ($param->allowsNull() && $datum === false) {
            return null;
        }

        // value object => matching class: leave as is
        if ($datum instanceof $class) {
            return $datum;
        }

        // any value => a class
        $subhandler = $transit->getHandler($class);
        if ($subhandler !== null) {
            // use handler for domain object
            return $transit->_newDomain($subhandler, $datum);
        }

        // @todo report the domain class and what converter was being used
        throw new Exception("No handler for \$" . $param->getName() . " typehint of {$class}.");
    }

    public function newDomainAggregate($transit, AggregateHandler $handler, Record $record)
    {
        // passes 1 & 2: data from record, after custom conversions
        $data = $this->convertSourceData($transit, $handler, $record);

        // pass 3: set types and create other domain objects as needed
        $args = [];
        foreach ($handler->getParameters() as $name => $param) {
            $args[] = $this->newDomainAggregateArgument($transit, $handler, $param, $record, $data);
        }

        // done
        return $handler->new($args);
    }

    protected function newDomainAggregateArgument(
        $transit,
        AggregateHandler $handler,
        ReflectionParameter $param,
        Record $record,
        array $data
    ) {
        $name = $param->getName();
        $class = $handler->getClass($name);

        // already an instance of the typehinted class?
        if ($data[$name] instanceof $class) {
            return $data[$name];
        }

        // for the Root Entity, create using the entire record
        if ($handler->isRoot($param)) {
            return $transit->newDomain($class, $record);
        }

        // for everything else, send only the matching value
        return $transit->newDomain($class, $data[$name]);
    }

    protected function convertSourceData($transit, Handler $handler, Record $record) : array
    {
        $data = [];

        // pass 1: data directly from source
        foreach ($handler->getParameters() as $name => $param) {
            $field = $transit->caseConverter->fromDomainToSource($name);
            if ($record->has($field)) {
                $data[$name] = $record->$field;
            } elseif ($param->isDefaultValueAvailable()) {
                $data[$name] = $param->getDefaultValue();
            } else {
                $data[$name] = null;
            }
        }

        // pass 2: convert source data to domain
        $this->fromSourceToDomain($record, $data);

        return $data;
    }

    public function fromSourceToDomain(Record $record, array &$parameters) : void
    {
    }

    public function fromDomainToSource(array &$properties, Record $record) : void
    {
    }
}

<?php
declare(strict_types=1);

namespace Atlas\Transit;

use Atlas\Mapper\Record;
use Atlas\Transit\Handler\Handler;
use Atlas\Transit\Handler\AggregateHandler;
use Atlas\Transit\Handler\EntityHandler;
use ReflectionParameter;

// (dis-)assembler? https://www.thesaurus.com/browse/assemble (put together)
// https://www.thesaurus.com/browse/convert (change; adapt)
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

    /**
     * @todo test this with value objects in the aggregate
     */
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

        foreach ($handler->getParameters() as $name => $param) {

            // custom approach
            $method = "__{$name}FromSource";
            if (method_exists($this, $method)) {
                $data[$name] = $this->$method($record);
                continue;
            }

            // default approach
            $field = $transit->caseConverter->fromDomainToSource($name);
            if ($record->has($field)) {
                $data[$name] = $record->$field;
            } elseif ($param->isDefaultValueAvailable()) {
                $data[$name] = $param->getDefaultValue();
            } else {
                $data[$name] = null;
            }
        }

        return $data;
    }

    public function updateSourceRecord($transit, $domain, Record $record) : void
    {
        $handler = $transit->getHandler($domain);

        $data = [];
        $default = 'updateSourceRecord' . $handler->getDomainMethod('From');
        foreach ($handler->getProperties() as $name => $property) {

            // custom approach
            $custom = "__{$name}IntoSource";
            if (method_exists($this, $custom)) {
                $this->$custom($record, $property->getValue($domain));
                continue;
            }

            $datum = $this->$default(
                $transit,
                $handler,
                $domain,
                $record,
                $property->getValue($domain)
            );

            $field = $transit->caseConverter->fromDomainToSource($name);
            if ($record->has($field)) {
                $record->$field = $datum;
            }
        }
    }

    // basically, we look to see if the $datum has a handler or not.
    // if it does, we update the $datum as well.
    protected function updateSourceRecordFromEntity(
        $transit,
        EntityHandler $handler,
        $domain,
        Record $record,
        $datum
    ) {
        if (! is_object($datum)) {
            return $datum;
        }

        $handler = $transit->getHandler($datum);
        if ($handler !== null) {
            return $transit->updateSource($datum);
        }

        return $datum;
    }

    protected function updateSourceRecordFromAggregate(
        $transit,
        AggregateHandler $handler,
        $domain,
        Record $record,
        $datum
    ) {
        if ($handler->isRoot($datum)) {
            return $this->updateSourceRecord($transit, $datum, $record);
        }

        return $this->updateSourceRecordFromEntity(
            $transit,
            $handler,
            $domain,
            $record,
            $datum
        );
    }
}

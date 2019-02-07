<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Record;
use Atlas\Transit\Inflector\Inflector;
use Atlas\Transit\Reflection\ValueObjectReflection;
use Atlas\Transit\Exception;
use ReflectionClass;
use ReflectionParameter;

/**
 * @todo Capture Record-specific params so we don't need to re-discover them
 * each time for the same Record type.
 */
class ValueObjectHandler extends Handler
{
    public function newDomain(Record $record, string $field)
    {
        if (isset($this->reflection->factory)) {
            return $this->reflection->factory->invoke(null, $record, $field);
        }

        $object = $this->newDomainSingle($record, $field)
            ?? $this->newDomainMultiple($record, $field)
            ?? $this->newDomainMultiplePrefixed($record, $field);

        if ($object === null) {
            throw new Exception("Cannot auto-create {$name} value object of {$domainClass}.");
        }

        return $object;
    }

    protected function newDomainSingle(Record $record, string $field) : ?object
    {
        // single constructor param with matching field name
        if ($this->reflection->parameterCount == 1 && $record->has($field)) {
            $domainClass = $this->reflection->domainClass;
            return new $domainClass($record->$field);
        }

        return null;
    }

    protected function newDomainMultiple(Record $record, string $field) : ?object
    {
        // look for fields without the domain property prefix;
        // e.g., street, city, state, zip
        $args = [];
        foreach ($this->reflection->parameters as $name => $param) {
            $field = $this->reflection->fromDomainToSource[$name];
            if (! $record->has($field)) {
                // must have all fields
                return null;
            }
            $args[] = $this->newDomainArgument($param, $record, $fixed);
        }

        $domainClass = $this->reflection->domainClass;
        return new $domainClass(...$args);
    }

    protected function newDomainMultiplePrefixed(Record $record, string $field) : ?object
    {
        // look for fields with the domain property to source field prefix;
        // e.g., address_street, address_city, address_state, address_zip
        $args = [];
        foreach ($this->reflection->parameters as $name => $param) {
            $fixed = $field . '_' . $this->reflection->fromDomainToSource[$name];
            if (! $record->has($fixed)) {
                // must have all fields
                return null;
            }
            $args[] = $this->newDomainArgument($param, $record, $fixed);
        }

        $domainClass = $this->reflection->domainClass;
        return new $domainClass(...$args);
    }

    protected function newDomainArgument(
        ReflectionParameter $param,
        Record $record,
        string $field
    ) {
        $datum = $record->$field;

        if ($param->allowsNull() && $datum === null) {
            return $datum;
        }

        $name = $param->getName();

        // non-class typehint?
        $type = $this->reflection->types[$name];
        if ($type !== null) {
            settype($datum, $type);
        }

        // class typehint?
        $class = $this->reflection->classes[$name];
        if ($class === null) {
            // note that this returns the non-class typed value as well
            return $datum;
        }

        // a handled domain class?
        $subhandler = $this->handlerLocator->get($class);
        return $subhandler instanceof ValueObjectHandler
            ? $subhandler->newDomain($record, $field)
            : $subhandler->newDomain($datum);
    }

    public function updateSource(
        Record $record,
        string $field,
        object $datum
    ) : void
    {
        if (isset($this->reflection->updater)) {
            $this->reflection->updater->invoke(null, $datum, $record, $field);
            return;
        }

        $updated = $this->updateSourceSingle($record, $field, $datum)
            ?? $this->updateSourceMultiple($record, $field, $datum)
            ?? $this->updateSourceMultiplePrefixed($record, $field, $datum);

        if ($updated === null) {
            $domainClass = $this->reflection->domainClass;
            throw new Exception("Cannot auto-update the source {$field} for value object of {$domainClass}.");
        }
    }

    protected function updateSourceSingle(
        Record $record,
        string $field,
        object $datum
    ) : ?bool
    {
        if ($this->reflection->parameterCount === 1 && $record->has($field)) {
            $rprops = $this->reflection->properties;
            $rprop = reset($rprops);
            $record->$field = $rprop->getValue($datum);
            return true;
        }

        return null;
    }

    protected function updateSourceMultiple(
        Record $record,
        string $field,
        object $datum
    ) : ?bool
    {
        // look for fields without the domain property prefix;
        // e.g., street, city, state, zip
        $args = [];
        foreach ($this->reflection->properties as $name => $rprop) {
            $field = $this->reflection->fromDomainToSource[$name];
            if (! $record->has($field)) {
                return null;
            }
            $args[$field] = $rprop->getValue($datum);
        }

        foreach ($args as $key => $val) {
            $record->$key = $val;
        }

        return true;
    }

    protected function updateSourceMultiplePrefixed(
        Record $record,
        string $field,
        object $datum
    ) : ?bool
    {
        // look for fields with the domain property prefix;
        // e.g., address_street, address_city, address_state, address_zip
        $args = [];
        foreach ($this->reflection->properties as $name => $rprop) {
            $rprop = $this->reflection->properties[$name];
            $fixed = $field . '_' . $this->reflection->fromDomainToSource[$name];
            if (! $record->has($fixed)) {
                return null;
            }
            $args[$fixed] = $rprop->getValue($datum);
        }

        foreach ($args as $key => $val) {
            $record->$key = $val;
        }

        return true;
    }
}

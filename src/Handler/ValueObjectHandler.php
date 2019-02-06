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
 *
 * @todo hand off to subhandlers so we can Value Object args for Value Objects
 * themselves, a la Money(int $amount, Currency $currency)
 */
class ValueObjectHandler extends Handler
{
    protected $newDomainArgumentStrategy = [];

    protected $updateSourceFieldObjectStrategy = [];

    public function newDomainArgument(
        Record $record,
        string $field
    ) : object
    {
        /* custom factory */
        if (isset($this->reflection->factory)) {
            return $this->reflection->factory->invoke(null, $record, $field);
        }

        $recordClass = get_class($record);
        $domainClass = $this->reflection->domainClass;

        if (isset($this->newDomainArgumentStrategy[$recordClass])) {
            $method = $this->newDomainArgumentStrategy[$recordClass];
            $args = $this->$method($record, $field);
            return new $domainClass(...$args);
        }

        $methods = [
            'newDomainArgumentSingle',
            'newDomainArgumentMultiplePrefixed',
            'newDomainArgumentMultipleNonPrefixed',
        ];

        foreach ($methods as $method) {
            $args = $this->$method($record, $field);
            if ($args !== null) {
                $this->newDomainArgumentStrategy[$recordClass] = $method;
                return new $domainClass(...$args);
            }
        }

        // cannot continue
        throw new Exception("Cannot auto-create {$name} value object of {$domainClass}.");
    }

    protected function newDomainArgumentSingle($record, $field)
    {
        /* single scalar constructor param with matching name */
        if (
            $this->reflection->parameterCount == 1
            && $record->has($field)
        ) {
            return [$record->$field];
        }

        return null;
    }

    protected function newDomainArgumentMultiplePrefixed($record, $field)
    {
        // look for fields with the domain property to source field prefix;
        // e.g., address_street, address_city, address_state, address_zip
        $args = [];
        foreach ($this->reflection->parameters as $name => $type) {
            $fixed = $this->reflection->inflector->fromDomainToSource("{$field}_{$name}");
            if (! $record->has($fixed)) {
                return null;
            }
            $arg = $record->$fixed;
            if ($type !== null) {
                settype($arg, $type);
            }
            $args[] = $arg;
        }

        return $args;
    }

    protected function newDomainArgumentMultipleNonPrefixed($record, $field)
    {
        // look for fields without the domain property prefix;
        // e.g., street, city, state, zip
        $args = [];
        foreach ($this->reflection->parameters as $name => $type) {
            $fixed = $this->reflection->inflector->fromDomainToSource($name);
            if (! $record->has($fixed)) {
                return null;
            }
            $arg = $record->$fixed;
            if ($type !== null) {
                settype($arg, $type);
            }
            $args[] = $arg;
        }

        return $args;
    }

    public function updateSourceFieldObject(
        Record $record,
        string $field,
        object $datum
    ) : void
    {
        /* custom updater */
        if (isset($this->reflection->updater)) {
            $this->reflection->updater->invoke(null, $datum, $record, $field);
            return;
        }

        $recordClass = get_class($record);

        if (isset($this->updateSourceFieldObjectStrategy[$recordClass])) {
            $method = $this->updateSourceFieldObjectStrategy[$recordClass];
            $this->$method($record, $field, $datum);
            return;
        }

        $methods = [
            'updateSourceFieldObjectSingle',
            'updateSourceFieldObjectMultiplePrefixed',
            'updateSourceFieldObjectMultipleNonPrefixed',
        ];

        foreach ($methods as $method) {
            if ($this->$method($record, $field, $datum)) {
                $this->updateSourceFieldObjectStrategy[$recordClass] = $method;
                return;
            }
        }

        // cannot continue
        $domainClass = $this->reflection->domainClass;
        throw new Exception("Cannot auto-update the source {$field} for value object of {$domainClass}.");
    }

    protected function updateSourceFieldObjectSingle(
        Record $record,
        string $field,
        object $datum
    ) : bool
    {
        if (
            $this->reflection->parameterCount === 1
            && $record->has($field)
        ) {
            $rprop = reset($this->reflection->properties);
            $record->$field = $rprop->getValue($datum);
            return true;
        }

        return false;
    }

    protected function updateSourceFieldObjectMultiplePrefixed(
        Record $record,
        string $field,
        object $datum
    ) : bool
    {
        /* one or more scalar constructor params, or no matching name */

        // look for fields with the domain property prefix;
        // e.g., address_street, address_city, address_state, address_zip
        $args = [];
        foreach ($this->reflection->parameters as $name => $type) {
            $rprop = $this->reflection->properties[$name];
            $fixed = $this->reflection->inflector->fromDomainToSource("{$field}_{$name}");
            if (! $record->has($fixed)) {
                return false;
            }
            $args[$fixed] = $rprop->getValue($datum);
        }

        foreach ($args as $key => $val) {
            $record->$key = $val;
        }

        return true;
    }

    protected function updateSourceFieldObjectMultipleNonPrefixed(
        Record $record,
        string $field,
        object $datum
    ) : bool
    {
        // look for fields without the domain property prefix;
        // e.g., street, city, state, zip
        $args = [];
        foreach ($this->reflection->parameters as $name => $type) {
            $rprop = $this->reflection->properties[$name];
            $fixed = $this->reflection->inflector->fromDomainToSource($name);
            if (! $record->has($fixed)) {
                return false;
            }
            $args[$fixed] = $rprop->getValue($datum);
        }

        foreach ($args as $key => $val) {
            $record->$key = $val;
        }

        return true;
    }
}

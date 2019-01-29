<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Record;
use Atlas\Transit\Inflector;
use Atlas\Transit\Reflections;
use Atlas\Transit\Exception;
use ReflectionClass;
use ReflectionParameter;

class ValueObjectHandler
{
    protected $reflection;

    public function __construct(object $reflection)
    {
        $this->reflection = $reflection;
    }

    public function newDomainArgument(
        Record $record,
        string $field
    ) : object
    {
        $class = $this->reflection->domainClass;

        /* custom factory */
        if (isset($this->reflection->fromSource)) {
            return $this->reflection->fromSource->invoke(null, $record, $field);
        }

        /* single scalar constructor param with matching name */
        if (
            $this->reflection->constructorParamCount == 1
            && $record->has($field)
        ) {
            return new $class($record->$field);
        }

        /* multiple scalar constructor params, or no matching name */

        // look for fields with the domain property to source field prefix;
        // e.g., address_street, address_city, address_state, address_zip
        $args = [];
        foreach ($this->reflection->constructorParams as $name => $type) {
            $fixed = $this->reflection->inflector->fromDomainToSource("{$field}_{$name}");
            if (! $record->has($fixed)) {
                break;
            }
            $arg = $record->$fixed;
            if ($type !== null) {
                settype($arg, $type);
            }
            $args[] = $arg;
        }

        if (count($args) === count($this->reflection->constructorParams)) {
            return new $class(...$args);
        }

        // look for fields without the domain property prefix;
        // e.g., street, city, state, zip
        $args = [];
        foreach ($this->reflection->constructorParams as $name => $type) {
            $fixed = $this->reflection->inflector->fromDomainToSource($name);
            if (! $record->has($fixed)) {
                break;
            }
            $arg = $record->$fixed;
            if ($type !== null) {
                settype($arg, $type);
            }
            $args[] = $arg;
        }

        if (count($args) === count($this->reflection->constructorParams)) {
            return new $class(...$args);
        }

        // cannot continue
        throw new Exception("Cannot auto-create {$name} value object of {$class}.");
    }

    public function updateSourceFieldObject(
        Record $record,
        string $field,
        object $datum
    ) : void
    {
        /* custom updater */
        if (isset($this->reflection->intoSource)) {
            $this->reflection->intoSource->invoke($datum, $record, $field);
            return;
        }

        /* one constructor param of matching name */
        if (
            $this->reflection->constructorParamCount === 1
            && $record->has($field)
        ) {
            $rprop = reset($this->reflection->properties);
            $record->$field = $rprop->getValue($datum);
            return;
        }

        /* one or more scalar constructor params, or no matching name */

        // look for fields with the domain property prefix;
        // e.g., address_street, address_city, address_state, address_zip
        $args = [];
        foreach ($this->reflection->constructorParams as $name => $type) {
            $rprop = $this->reflection->properties[$name];
            $fixed = $this->reflection->inflector->fromDomainToSource("{$field}_{$name}");
            if (! $record->has($fixed)) {
                break;
            }
            $args[$fixed] = $rprop->getValue($datum);
        }

        if (count($args) === $this->reflection->constructorParamCount) {
            foreach ($args as $key => $val) {
                $record->$key = $val;
            }
            return;
        }

        // look for fields without the domain property prefix;
        // e.g., street, city, state, zip
        $args = [];
        foreach ($this->reflection->constructorParams as $name => $type) {
            $rprop = $this->reflection->properties[$name];
            $fixed = $this->reflection->inflector->fromDomainToSource($name);
            if (! $record->has($fixed)) {
                break;
            }
            $args[$fixed] = $rprop->getValue($datum);
        }

        if (count($args) === $this->reflection->constructorParamCount) {
            foreach ($args as $key => $val) {
                $record->$key = $val;
            }
            return;
        }

        // cannot continue
        throw new Exception("Cannot extract {$name} value from domain object {$class}; does not have a property matching the constructor parameter.");
    }
}

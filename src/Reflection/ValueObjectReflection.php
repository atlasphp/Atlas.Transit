<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

use Atlas\Transit\Inflector\Inflector;
use ReflectionClass;

class ValueObjectReflection extends Reflection
{
    public $type = 'ValueObject';
    public $inflector;
    public $factory;
    public $updater;
    public $parameterCount = 0;
    public $parameters = [];
    public $properties = [];

    public function __construct(
        ReflectionClass $r,
        ReflectionLocator $reflectionLocator
    ) {
        parent::__construct($r, $reflectionLocator);
        $this->inflector = $reflectionLocator->getInflector();

        $this->setMethod($r, 'factory');
        $this->setMethod($r, 'updater');

        $rctor = $r->getConstructor();
        if ($rctor !== null) {
            $this->parameterCount = $rctor->getNumberOfParameters();
        }

        foreach ($rctor->getParameters() as $rparam) {
            $name = $rparam->getName();
            $type = null;
            if ($rparam->hasType()) {
                $type = $rparam->getType()->getName();
            }
            $this->parameters[$name] = $type;

            if ($r->hasProperty($name)) {
                $rprop = $r->getProperty($name);
                $rprop->setAccessible(true);
                $this->properties[$name] = $rprop;
            }
        }
    }

    protected function setMethod(ReflectionClass $r, string $property) : void
    {
        $found = preg_match(
            '/^\s*\*\s*@Atlas\\\\Transit\\\\' . ucfirst($property) . '[ \t](\S*?)::(\S*)/m',
            $this->docComment,
            $matches
        );

        if (empty($matches)) {
            return;
        }

        if ($matches[1] !== 'self') {
            // @todo blow up if class is not there
            $r = new ReflectionClass($matches[1]);
        }

        $method = $matches[2];
        if (substr($method, -2) == '()') {
            $method = substr($method, 0, -2);
        }

        // @todo blow up if methdd is not there
        $this->$property = $r->getMethod($method);
        $this->$property->setAccessible(true);
    }
}

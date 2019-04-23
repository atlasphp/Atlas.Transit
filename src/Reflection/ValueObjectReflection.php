<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

use Atlas\Transit\Inflector\Inflector;
use ReflectionClass;

class ValueObjectReflection extends Reflection
{
    use ParametersTrait;

    public $type = 'ValueObject';
    public $factory;
    public $updater;

    public function __construct(
        ReflectionClass $r,
        ReflectionLocator $reflectionLocator
    ) {
        parent::__construct($r, $reflectionLocator);
        $this->setParameters($r, $reflectionLocator);
        $this->setMethod($r, 'factory');
        $this->setMethod($r, 'updater');
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

        // @todo blow up if method is not there
        $this->$property = $r->getMethod($method);
        $this->$property->setAccessible(true);
    }
}

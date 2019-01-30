<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

use Atlas\Transit\Inflector\Inflector;
use ReflectionClass;

class ValueObjectReflection extends Reflection
{
    public $type = 'ValueObject';
    public $inflector;
    public $fromSource;
    public $intoSource;
    public $parameterCount = 0;
    public $parameters = [];
    public $properties = [];

    public function __construct(ReflectionClass $r, string $docComment, string $sourceNamespace, Inflector $inflector)
    {
        parent::__construct($r, $docComment, $sourceNamespace, $inflector);

        if ($r->hasMethod('__transitFromSource')) {
            $rmethod = $r->getMethod('__transitFromSource');
            $rmethod->setAccessible(true);
            $this->fromSource = $rmethod;
        }

        if ($r->hasMethod('__transitIntoSource')) {
            $rmethod = $r->getMethod('__transitIntoSource');
            $rmethod->setAccessible(true);
            $this->intoSource = $rmethod;
        }

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
}

<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

use Atlas\Transit\Inflector\Inflector;
use ReflectionClass;

class AggregateReflection extends ParametersReflection
{
    protected $type = 'Aggregate';
    protected $rootClass;

    public function __construct(
        ReflectionClass $r,
        ReflectionLocator $reflectionLocator
    ) {
        parent::__construct($r, $reflectionLocator);
        $this->rootClass = reset($this->parameters)->getClass()->getName();
        $this->mapperClass = $reflectionLocator->get($this->rootClass)->mapperClass;

        $tableClass = $this->mapperClass . 'Table';
        $this->autoincColumn = $tableClass::AUTOINC_COLUMN;
    }
}

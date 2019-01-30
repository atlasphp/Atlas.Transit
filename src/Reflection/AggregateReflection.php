<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

use Atlas\Transit\Inflector\Inflector;
use ReflectionClass;

class AggregateReflection extends EntityReflection
{
    public $type = 'Aggregate';
    public $rootClass;

    public function __construct(
        ReflectionClass $r,
        ReflectionLocator $reflectionLocator
    ) {
        parent::__construct($r, $reflectionLocator);
        $this->rootClass = reset($this->parameters)->getClass()->getName();
        $this->mapperClass = $reflectionLocator->get($this->rootClass)->mapperClass;
    }
}

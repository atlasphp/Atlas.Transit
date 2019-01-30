<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

use Atlas\Transit\Inflector\Inflector;
use ReflectionClass;

class AggregateReflection extends EntityReflection
{
    public $type = 'Aggregate';
    public $rootClass;

    public function __construct(ReflectionClass $r, string $docComment, string $sourceNamespace, Inflector $inflector)
    {
        parent::__construct($r, $docComment, $sourceNamespace, $inflector);
        $this->rootClass = reset($this->parameters)->getClass()->getName();
    }
}

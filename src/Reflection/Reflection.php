<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

use Atlas\Transit\Inflector\Inflector;
use ReflectionClass;

abstract class Reflection
{
    public $domainClass;
    public $docComment;
    public $inflector;

    public function __construct(ReflectionClass $r, string $docComment, string $sourceNamespace, Inflector $inflector)
    {
        $this->domainClass = $r->getName();
        $this->docComment = $docComment;
        $this->inflector = $inflector;
    }
}

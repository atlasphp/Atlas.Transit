<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

use Atlas\Transit\Exception;
use Atlas\Transit\Inflector\Inflector;
use ReflectionClass;

class ReflectionLocator
{
    protected $instances = [];

    protected $sourceNamespace;

    protected $inflector;

    public function __construct(
        string $sourceNamespace,
        Inflector $inflector
    ) {
        $this->sourceNamespace = rtrim($sourceNamespace, '\\');
        $this->inflector = $inflector;
    }

    public function getSourceNamespace() : string
    {
        return $this->sourceNamespace;
    }

    public function getInflector() : Inflector
    {
        return $this->inflector;
    }

    public function get(string $domainClass) : object
    {
        if (! isset($this->instances[$domainClass])) {
            $this->instances[$domainClass] = $this->newReflection($domainClass);
        }

        return $this->instances[$domainClass];
    }

    protected function newReflection(string $domainClass) : Reflection
    {
        $r = new ReflectionClass($domainClass);

        $rdoc = $r->getDocComment();
        if ($rdoc === false) {
            throw new Exception("$domainClass not annotated for Transit.");
        }

        $found = preg_match(
            '/^\s*\*\s*@Atlas\\\\Transit\\\\(Entity|Aggregate|Collection|ValueObject)\s+/m',
            $rdoc,
            $matches
        );

        if (! $found) {
            throw new Exception("$domainClass not annotated for Transit.");
        }

        $type = trim($matches[1]);
        $class = "Atlas\Transit\Reflection\\{$type}Reflection";
        $reflection = new $class($r, $this);
        return $reflection;
    }
}

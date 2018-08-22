<?php
namespace Atlas\Transit\Handler;

use Atlas\Transit\Exception;
use ReflectionParameter;

class AggregateHandler extends EntityHandler
{
    protected $root;

    public function getDomainMethod(string $method) : string
    {
        return $method . 'Aggregate';
    }

    public function setRoot(string $root)
    {
        $this->root = $root;
        return $this;
    }

    public function isRoot($spec)
    {
        if ($this->root === null) {
            throw new Exception("No aggregate root specified.");
        }

        if ($spec instanceof ReflectionParameter) {
            $class = $spec->getClass()->getName() ?? '';
            return $this->root === $class;
        }

        if (is_object($spec)) {
            return $this->root === get_class($spec);
        }

        return $this->root === $spec;
    }
}

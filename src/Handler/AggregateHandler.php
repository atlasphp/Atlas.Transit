<?php
namespace Atlas\Transit\Handler;

use Atlas\Transit\Exception;
use ReflectionParameter;

class AggregateHandler extends EntityHandler
{
    protected $rootClass;

    protected function setMapperClass(string $domainClass, string $aggregateNamespace, string $sourceNamespace)
    {
        $this->rootClass = reset($this->parameters)->getClass()->getName();
        $entityNamespace = substr($aggregateNamespace, 0, -10) . 'Entity\\';
        parent::setMapperClass($this->rootClass, $entityNamespace, $sourceNamespace);
    }

    public function getDomainMethod(string $method) : string
    {
        return $method . 'Aggregate';
    }

    public function isRoot($spec)
    {
        if ($spec instanceof ReflectionParameter) {
            $class = $spec->getClass()->getName() ?? '';
            return $this->rootClass === $class;
        }

        if (is_object($spec)) {
            return $this->rootClass === get_class($spec);
        }

        return $this->rootClass === $spec;
    }
}

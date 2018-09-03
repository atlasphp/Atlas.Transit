<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Transit\Exception;
use ReflectionParameter;

class AggregateHandler extends EntityHandler
{
    protected $rootClass;

    public function __construct(string $domainClass, string $mapperClass)
    {
        parent::__construct($domainClass, $mapperClass);
        $this->rootClass = reset($this->parameters)->getClass()->getName();
    }

    public function getDomainMethod(string $method) : string
    {
        return $method . 'Aggregate';
    }

    public function isRoot($spec) : bool
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

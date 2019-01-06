<?php
namespace Atlas\Transit\Handler;

use Atlas\Transit\CaseConverter;
use Atlas\Transit\Casing\SnakeCase;
use Atlas\Transit\Casing\CamelCase;
use Atlas\Transit\Exception;

class HandlerLocator
{
    protected $instances = [];

    protected $handlerFactory;

    public function __construct(HandlerFactory $handlerFactory)
    {
        $this->handlerFactory = $handlerFactory;
    }

    public function get($domainClass) : ?Handler
    {
        if (is_object($domainClass)) {
            $domainClass = get_class($domainClass);
        }

        if (! class_exists($domainClass)) {
            throw new Exception("Domain class '{$domainClass}' does not exist.");
        }

        if (! array_key_exists($domainClass, $this->instances)) {
            $this->instances[$domainClass] = $this->handlerFactory->new($domainClass);
        }

        return $this->instances[$domainClass];
    }
}

<?php
declare(strict_types=1);

namespace Atlas\Transit;

use Atlas\Mapper\MapperSelect;
use Atlas\Transit\Handler\Handler;
use Atlas\Transit\Handler\CollectionHandler;

class TransitSelect
{
    protected $mapperSelect;

    protected $handler;

    public function __construct(
        MapperSelect $mapperSelect,
        Handler $handler
    ) {
        $this->mapperSelect = $mapperSelect;
        $this->handler = $handler;
    }

    public function __call(string $method, array $params)
    {
        $result = $this->mapperSelect->$method(...$params);
        return ($result === $this->mapperSelect) ? $this : $result;
    }

    public function __clone()
    {
        $this->mapperSelect = clone $this->mapperSelect;
    }

    public function fetchDomain() : ?object
    {
        $fetchMethod = 'fetchRecord';
        if ($this->handler instanceof CollectionHandler) {
            $fetchMethod = 'fetchRecordSet';
        }

        $source = $this->mapperSelect->$fetchMethod();
        if ($source === null) {
            return null;
        }

        return $this->handler->newDomain($source);
    }
}

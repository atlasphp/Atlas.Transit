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

    protected $fetchMethod = 'fetchRecord';

    protected $storage;

    public function __construct(
        Handler $handler,
        $storage
    ) {
        $this->mapperSelect = $handler->newSelect();
        $this->handler = $handler;
        if ($this->handler instanceof CollectionHandler) {
            $this->fetchMethod = 'fetchRecordSet';
        }
        $this->storage = $storage;
    }

    public function __call(string $method, array $params)
    {
        $result = call_user_func_array([$this->mapperSelect, $method], $params);
        return ($result === $this->mapperSelect) ? $this : $result;
    }

    public function __clone()
    {
        $this->mapperSelect = clone $this->mapperSelect;
    }

    public function fetchDomain()
    {
        $source = $this->mapperSelect->{$this->fetchMethod}();
        if ($source === null) {
            return null;
        }
        return $this->handler->newDomain($source, $this->storage);
    }
}

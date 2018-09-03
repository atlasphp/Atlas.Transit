<?php
declare(strict_types=1);

namespace Atlas\Transit;

use Atlas\Mapper\MapperSelect;
use Atlas\Transit\Handler\Handler;

class TransitSelect
{
    protected $transit;

    protected $mapperSelect;

    protected $fetchMethod;

    protected $domainClass;

    public function __construct(
        Transit $transit,
        MapperSelect $mapperSelect,
        string $fetchMethod,
        string $domainClass
    ) {
        $this->transit = $transit;
        $this->mapperSelect = $mapperSelect;
        $this->fetchMethod = $fetchMethod;
        $this->domainClass = $domainClass;
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
        $method = $this->fetchMethod;
        $source = $this->$method();
        if (! $source) {
            return null;
        }
        return $this->transit->newDomain($this->domainClass, $source);
    }
}

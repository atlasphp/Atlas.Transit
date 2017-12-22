<?php
namespace Atlas\Transit\Handler;

abstract class Handler
{
    protected $domainClass;

    protected $mapperClass;

    protected $factory;

    protected $updater;

    abstract function __construct(string $domainClass, string $mapperClass);

    public function getDomainClass() : string
    {
        return $this->domainClass;
    }

    public function getMapperClass() : string
    {
        if ($this->mapperClass === null) {
            throw new Exception("no source mapper class for {$this->domainClass}");
        }

        return $this->mapperClass;
    }

    abstract public function getSourceMethod(string $method) : string;

    abstract public function getDomainMethod(string $method) : string;

    // function ($source) : domain
    public function setFactory(callable $factory)
    {
        $this->factory = $factory;
        return $this;
    }

    // function ($source, $domain) : void
    public function setUpdater(callable $updater)
    {
        $this->updater = $updater;
        return $this;
    }

    public function getFactory() : ?callable
    {
        return $this->factory;
    }

    public function getUpdater() : ?callable
    {
        return $this->updater;
    }
}

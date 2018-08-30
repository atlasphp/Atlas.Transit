<?php
namespace Atlas\Transit\Handler;

abstract class Handler
{
    protected $domainClass;

    protected $mapperClass;

    protected function setMapperClass(string $domainClass, string $entityNamespace, string $sourceNamespace)
    {
        $class = $sourceNamespace . substr(
            $domainClass, strlen($entityNamespace)
        );
        $parts = explode('\\', $class);
        array_pop($parts);
        $final = end($parts);
        $this->mapperClass = implode('\\', $parts) . '\\' . $final;
    }

    public function getDomainClass() : string
    {
        return $this->domainClass;
    }

    public function getMapperClass() : string
    {
        return $this->mapperClass;
    }

    abstract public function getSourceMethod(string $method) : string;

    abstract public function getDomainMethod(string $method) : string;
}

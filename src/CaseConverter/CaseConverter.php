<?php
namespace Atlas\Transit\CaseConverter;

class CaseConverter
{
    protected $sourceCase;

    protected $domainCase;

    public function __construct(ACase $sourceCase, ACase $domainCase)
    {
        $this->sourceCase = $sourceCase;
        $this->domainCase = $domainCase;
    }

    public function fromDomainToSource(string $name) : string
    {
        return $this->sourceCase->implode($this->domainCase->explode($name));
    }

    public function fromSourceToDomain(string $name) : string
    {
        return $this->domainCase->implode($this->sourceCase->explode($name));
    }
}

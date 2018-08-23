<?php
namespace Atlas\Transit;

use Atlas\Transit\Casing\Casing;

class CaseConverter
{
    protected $recordCase;

    protected $domainCase;

    public function __construct(Casing $recordCase, Casing $domainCase)
    {
        $this->recordCase = $recordCase;
        $this->domainCase = $domainCase;
    }

    public function fromDomainToRecord(string $name) : string
    {
        return $this->recordCase->implode($this->domainCase->explode($name));
    }

    public function fromRecordToDomain(string $name) : string
    {
        return $this->domainCase->implode($this->recordCase->explode($name));
    }
}

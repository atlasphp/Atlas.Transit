<?php
declare(strict_types=1);

namespace Atlas\Transit;

use Atlas\Transit\Casing\Casing;

class Inflector
{
    protected $recordCasing;

    protected $domainCasing;

    public function __construct(Casing $recordCasing, Casing $domainCasing)
    {
        $this->recordCasing = $recordCasing;
        $this->domainCasing = $domainCasing;
    }

    public function fromDomainToSource(string $name) : string
    {
        return $this->recordCasing->implode($this->domainCasing->explode($name));
    }

    public function fromSourceToDomain(string $name) : string
    {
        return $this->domainCasing->implode($this->recordCasing->explode($name));
    }
}

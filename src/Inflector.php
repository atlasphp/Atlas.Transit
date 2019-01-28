<?php
declare(strict_types=1);

namespace Atlas\Transit;

use Atlas\Transit\Casing\Casing;

class Inflector
{
    protected $sourceCasing;

    protected $domainCasing;

    public function __construct(Casing $sourceCasing, Casing $domainCasing)
    {
        $this->sourceCasing = $sourceCasing;
        $this->domainCasing = $domainCasing;
    }

    public function fromDomainToSource(string $name) : string
    {
        return $this->sourceCasing->implode($this->domainCasing->explode($name));
    }

    public function fromSourceToDomain(string $name) : string
    {
        return $this->domainCasing->implode($this->sourceCasing->explode($name));
    }
}

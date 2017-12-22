<?php
namespace Atlas\Transit\Domain;

class NamedEntity extends DomainObject
{
    protected $id;
    protected $name;

    public function __construct(
        int $id,
        string $name
    ) {
        $this->id = $id;
        $this->name = $name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }
}

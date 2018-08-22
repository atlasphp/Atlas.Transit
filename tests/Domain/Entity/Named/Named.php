<?php
namespace Atlas\Transit\Domain;

use Atlas\Transit\Domain\Entity\Entity;

class Named extends Entity
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

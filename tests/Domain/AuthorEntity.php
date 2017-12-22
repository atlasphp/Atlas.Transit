<?php
namespace Atlas\Transit\Domain;

class AuthorEntity extends DomainObject
{
    protected $authorId;
    protected $name;

    public function __construct(
        int $authorId,
        string $name,
        $fakeField = 'fake' // makes sure that defaults get populated
    ) {
        $this->authorId = $authorId;
        $this->name = $name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }
}

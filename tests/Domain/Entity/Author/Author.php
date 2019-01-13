<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Entity\Author;

use Atlas\Transit\Domain\Entity\Entity;
use Atlas\Transit\Domain\Value\Email;

class Author extends Entity
{
    protected $authorId;
    protected $name;

    public function __construct(
        string $name,
        $fakeField = 'fake', // makes sure that defaults get populated, and mixed typehints work
        int $authorId = null
    ) {
        $this->name = $name;
        $this->authorId = $authorId;
    }

    public function setName($name)
    {
        $this->name = $name;
    }
}

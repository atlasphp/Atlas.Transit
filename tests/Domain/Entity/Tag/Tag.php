<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Entity\Tag;

use Atlas\Transit\Domain\Entity\Entity;

class Tag extends Entity
{
    protected $tagId;
    protected $name;

    public function __construct(
        string $name,
        int $tagId = null
    ) {
        $this->name = $name;
        $this->tagId = $tagId;
    }
}

<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Entity\Reply;

use Atlas\Transit\Domain\Entity\Entity;
use Atlas\Transit\Domain\Entity\Author\Author;

class Reply extends Entity
{
    protected $replyId;
    protected $author;
    protected $body;

    public function __construct(
        Author $author,
        string $body,
        int $replyId = null
    ) {
        $this->author = $author;
        $this->body = $body;
        $this->replyId = $replyId;
    }
}

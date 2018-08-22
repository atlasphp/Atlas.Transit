<?php
namespace Atlas\Transit\Domain\Entity\Reply;

use Atlas\Transit\Domain\Entity\Entity;
use Atlas\Transit\Domain\Entity\Author\Author;

class Reply extends Entity
{
    protected $replyId;
    protected $body;
    protected $author;

    public function __construct(
        int $replyId,
        string $body,
        Author $author
    ) {
        $this->replyId = $replyId;
        $this->body = $body;
        $this->author = $author;
    }
}

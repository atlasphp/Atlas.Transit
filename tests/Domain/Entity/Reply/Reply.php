<?php
namespace Atlas\Transit\Domain\Entity\Reply;

use Atlas\Transit\Domain\Entity\Entity;
use Atlas\Transit\Domain\Entity\Author\Author;
use Atlas\Transit\Domain\Value\DateTimeValue;

class Reply extends Entity
{
    protected $replyId;
    protected $body;
    protected $author;
    protected $createdAt;

    public function __construct(
        Author $author,
        DateTimeValue $createdAt
        string $body,
        int $replyId = null
    ) {
        $this->author = $author;
        $this->createdAt = $createdAt;
        $this->body = $body;
        $this->replyId = $replyId;
    }
}

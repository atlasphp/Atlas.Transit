<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Entity\Reply;

use Atlas\Transit\Domain\Entity\Entity;
use Atlas\Transit\Domain\Entity\Author\Author;
use Atlas\Transit\Domain\Value\DateTime;

class Reply extends Entity
{
    protected $replyId;
    protected $author;
    protected $createdAt;
    protected $body;

    public function __construct(
        Author $author,
        DateTime $createdAt,
        string $body,
        int $replyId = null
    ) {
        $this->author = $author;
        $this->createdAt = $createdAt;
        $this->body = $body;
        $this->replyId = $replyId;
    }
}

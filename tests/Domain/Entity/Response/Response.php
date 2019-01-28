<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Entity\Response;

use Atlas\Transit\Domain\Entity\Entity;
use Atlas\Transit\Domain\Entity\Author\Author;

/**
 * @Atlas\Transit\Entity
 * @Atlas\Transit\Entity\Mapper Atlas\Testing\DataSource\Reply\Reply
 */
class Response extends Entity
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

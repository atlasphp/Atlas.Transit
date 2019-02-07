<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Entity\Thread;

use Atlas\Transit\Domain\Entity\Entity;
use Atlas\Transit\Domain\Entity\Author\Author;
use Atlas\Transit\Domain\Value\DateTime;

/**
 * @Atlas\Transit\Entity
 */
class Thread extends Entity
{
    protected $threadId;
    protected $author;
    protected $subject;
    protected $body;

    public function __construct(
        Author $author,
        string $subject,
        string $body,
        int $threadId = null
    ) {
        $this->author = $author;
        $this->subject = $subject;
        $this->body = $body;
        $this->threadId = $threadId;
    }

    public function setSubject($subject)
    {
        $this->subject = $subject;
    }
}

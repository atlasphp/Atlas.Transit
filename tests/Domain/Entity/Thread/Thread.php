<?php
namespace Atlas\Transit\Domain\Entity\Thread;

use Atlas\Transit\Domain\Entity\Entity;
use Atlas\Transit\Domain\Entity\Author\Author;

class Thread extends Entity
{
    protected $threadId;
    protected $subject;
    protected $body;
    protected $author;

    public function __construct(
        int $threadId,
        string $subject,
        string $body,
        Author $author
    ) {
        $this->threadId = $threadId;
        $this->subject = $subject;
        $this->body = $body;
        $this->author = $author;
    }

    public function getId()
    {
        return $this->threadId;
    }

    public function setSubject($subject)
    {
        $this->subject = $subject;
    }
}

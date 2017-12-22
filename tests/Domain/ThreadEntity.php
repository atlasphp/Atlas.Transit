<?php
namespace Atlas\Transit\Domain;

class ThreadEntity extends DomainObject
{
    protected $threadId;
    protected $subject;
    protected $body;
    protected $author;

    public function __construct(
        int $threadId,
        string $subject,
        string $body,
        AuthorEntity $author
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

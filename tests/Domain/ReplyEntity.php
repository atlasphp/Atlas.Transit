<?php
namespace Atlas\Transit\Domain;

class ReplyEntity extends DomainObject
{
    protected $replyId;
    protected $body;
    protected $author;

    public function __construct(
        int $replyId,
        string $body,
        AuthorEntity $author
    ) {
        $this->replyId = $replyId;
        $this->body = $body;
        $this->author = $author;
    }
}

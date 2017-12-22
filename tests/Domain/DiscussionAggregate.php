<?php
namespace Atlas\Transit\Domain;

class DiscussionAggregate extends DomainObject
{
    protected $thread;
    protected $replies;

    public function __construct(
        ThreadEntity $thread,
        ReplyEntityCollection $replies
    ) {
        $this->thread = $thread;
        $this->replies = $replies;
    }

    public function setThreadSubject($subject)
    {
        $this->thread->setSubject($subject);
    }
}

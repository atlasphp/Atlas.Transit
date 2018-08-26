<?php
namespace Atlas\Transit\Domain\Aggregate;

use Atlas\Transit\Domain\Entity\Thread\Thread;
use Atlas\Transit\Domain\Entity\Reply\ReplyCollection;

class Discussion
{
    protected $thread;
    protected $replies;

    public function __construct(
        Thread $thread,
        ReplyCollection $replies
    ) {
        $this->thread = $thread;
        $this->replies = $replies;
    }

    public function getArrayCopy()
    {
        $copy = [];
        foreach (get_object_vars($this) as $key => $val) {
            if ($val instanceof Entity || $val instanceof EntityCollection) {
                $copy[$key] = $val->getArrayCopy();
            } else {
                $copy[$key] = $val;
            }
        }
        return $copy;
    }

    public function setThreadSubject($subject)
    {
        $this->thread->setSubject($subject);
    }
}

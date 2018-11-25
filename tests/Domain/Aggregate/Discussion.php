<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Aggregate;

use Atlas\Transit\Domain\Entity\Entity;
use Atlas\Transit\Domain\Entity\EntityCollection;
use Atlas\Transit\Domain\Entity\Reply\ReplyCollection;
use Atlas\Transit\Domain\Entity\Tag\TagCollection;
use Atlas\Transit\Domain\Entity\Thread\Thread;

class Discussion
{
    protected $thread;
    protected $tags;
    protected $replies;

    public function __construct(
        Thread $thread,
        TagCollection $tags,
        ReplyCollection $replies
    ) {
        $this->thread = $thread;
        $this->tags = $tags;
        $this->replies = $replies;
    }

    public function getArrayCopy()
    {
        $copy = [];
        foreach (get_object_vars($this) as $key => $val) {
            if (is_callable([$val, 'getArrayCopy'])) {
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

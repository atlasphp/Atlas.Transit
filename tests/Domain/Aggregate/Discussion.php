<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Aggregate;

use Atlas\Transit\Domain\Entity\Response\Responses;
use Atlas\Transit\Domain\Entity\Tag\TagCollection;
use Atlas\Transit\Domain\Entity\Thread\Thread;

/**
 * @Atlas\Transit\Aggregate
 * @Atlas\Transit\Parameter $responses replies
 */
class Discussion
{
    protected $thread;
    protected $tags;
    protected $responses;

    public function __construct(
        Thread $thread,
        TagCollection $tags,
        Responses $responses
    ) {
        $this->thread = $thread;
        $this->tags = $tags;
        $this->responses = $responses;
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

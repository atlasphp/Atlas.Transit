<?php
namespace Atlas\Transit\Domain;

use ArrayIterator;
use IteratorAggregate;

abstract class DomainObjectCollection
extends DomainObject
implements IteratorAggregate
{
    protected $members;

    public function __construct(array $members)
    {
        $this->members = $members;
    }

    public function getIterator()
    {
        return new ArrayIterator($this->members);
    }

    public function getArrayCopy()
    {
        $copy = [];
        foreach ($this as $member) {
            $copy[] = $member->getArrayCopy();
        }
        return $copy;
    }
}

<?php
namespace Atlas\Transit\DataSource\Fake;

use Atlas\Mapper\Mapper;
use Atlas\Mapper\Record;

class Fake extends Mapper
{
    public function persist(Record $record, ?\SplObjectStorage $tracker = null) : void
    {
        // do nothing
    }
}

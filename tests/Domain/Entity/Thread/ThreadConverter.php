<?php
namespace Atlas\Transit\Domain\Entity\Thread;

use Atlas\Mapper\Record;
use Atlas\Transit\DataConverter;
use Atlas\Transit\Domain\Value\DateTimeValue;

class ThreadConverter extends DataConverter
{
    public function fromSourceToDomain(Record $record, array &$parameters) : void
    {
        $parameters['createdAt'] = new DateTimeValue('1970-08-08');
    }
}

<?php
namespace Atlas\Transit\Domain\Entity\Reply;

use Atlas\Mapper\Record;
use Atlas\Transit\DataConverter;
use Atlas\Transit\Domain\Value\DateTimeValue;

class ReplyConverter extends DataConverter
{
    public function fromRecordToDomain($record, array &$parameters) : void
    {
        $parameters['createdAt'] = new DateTimeValue('1979-11-07');
    }
}

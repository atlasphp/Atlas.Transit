<?php
namespace Atlas\Transit\Domain\Entity\Reply;

use Atlas\Transit\DataConverter;
use Atlas\Transit\Domain\Value\DateTimeValue;

class ReplyConverter extends DataConverter
{
    public function fromRecordToEntity(array &$values) : void
    {
        $values['createdAt'] = new DateTimeValue('1979-11-07');
    }

    public function fromEntityToRecord(array &$values) : void
    {
        $values['created_at'] = $values['created_at']->get();
    }
}

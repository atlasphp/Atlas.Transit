<?php
namespace Atlas\Transit\Domain\Entity\Thread;

use Atlas\Transit\DataConverter;
use Atlas\Transit\Domain\Value\DateTimeValue;

class ThreadConverter extends DataConverter
{
    public function fromRecordToEntity(array &$values) : void
    {
        $values['createdAt'] = new DateTimeValue('1970-08-08');
    }

    public function fromEntityToRecord(array &$values) : void
    {
        $values['created_at'] = $values['created_at']->get();
    }
}

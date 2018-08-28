<?php
namespace Atlas\Transit\Domain\Entity\Author;

use Atlas\Transit\DataConverter;
use Atlas\Transit\Domain\Value\EmailValue;

class AuthorConverter extends DataConverter
{
    public function fromRecordToEntity(array &$values) : void
    {
        $values['email'] = new EmailValue(
            strtolower($values['name']) . '@example.com'
        );
    }

    public function fromEntityToRecord(array &$values) : void
    {
        $values['email'] = $values['email']->get();
    }
}

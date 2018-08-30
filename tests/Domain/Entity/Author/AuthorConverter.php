<?php
namespace Atlas\Transit\Domain\Entity\Author;

use Atlas\Mapper\Record;
use Atlas\Transit\DataConverter;
use Atlas\Transit\Domain\Value\EmailValue;

class AuthorConverter extends DataConverter
{
    public function fromRecordToDomain(Record $record, array &$parameters) : void
    {
        $parameters['email'] = new EmailValue(
            strtolower($record->name) . '@example.com'
        );
    }
}

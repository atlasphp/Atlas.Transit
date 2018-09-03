<?php
namespace Atlas\Transit\Domain\Entity\Author;

use Atlas\Mapper\Record;
use Atlas\Transit\DataConverter;
use Atlas\Transit\Domain\Value\Email;

class AuthorConverter extends DataConverter
{
    public function fromSourceToDomain(Record $record, array &$parameters) : void
    {
        $parameters['email'] = new Email(
            strtolower($record->name) . '@example.com'
        );
    }
}

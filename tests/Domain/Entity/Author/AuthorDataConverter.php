<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Entity\Author;

use Atlas\Mapper\Record;
use Atlas\Transit\DataConverter;
use Atlas\Transit\Domain\Value\Email;

class AuthorDataConverter extends DataConverter
{
    protected function __emailFromSource(Record $record)
    {
        return new Email(
            strtolower($record->name) . '@example.com'
        );
    }
}

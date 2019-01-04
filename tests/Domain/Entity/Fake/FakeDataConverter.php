<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Entity\Fake;

use Atlas\Mapper\Record;
use Atlas\Transit\DataConverter;
use Atlas\Transit\Domain\Value\Address;
use Atlas\Transit\Domain\Value\DateTimeWithZone;
use Atlas\Transit\Domain\Value\Email;
use stdClass;
use DateTimeZone;

class FakeDataConverter extends DataConverter
{
    public function __emailAddressFromSource(Record $record)
    {
        return new Email($record->email_address);
    }

    public function __addressFromSource(Record $record)
    {
        return new Address(
            $record->address->street,
            $record->address->city,
            $record->address->region,
            $record->address->postcode
        );
    }

    public function __dateTimeGroupFromSource(Record $record)
    {
        return new DateTimeWithZone(
            $record->date_time,
            new \DateTimeZone($record->time_zone)
        );
    }

    public function __jsonBlobFromSource(Record $record)
    {
        return json_decode($record->json_blob);
    }

    public function __emailAddressIntoSource(Record $record, Email $emailAddress)
    {
        $record->email_address = $emailAddress->get();
    }

    public function __addressIntoSource(Record $record, Address $address)
    {
        // now, what if the Domain object is new? Then the $record won't
        // have a related address record yet. this means either a special
        // check-and-create here, or a Mapper Relationship type that always
        // creates a Record object? A la oneToOne->always() manyToOne->always().
        // the problem with always() is that it means you need to descend into
        // *those* relateds to find always() as well. (or ->required().)
        $record->address->street = $address->getStreet();
        $record->address->city = $address->getCity();
        $record->address->region = $address->getState();
        $record->address->postcode = $address->getZip();
    }

    public function __dateTimeGroupIntoSource(Record $record, DateTimeWithZone $dateTimeGroup)
    {
        $record->date_time = $dateTimeGroup->getDateTime();
        $record->time_zone = $dateTimeGroup->getZone();
    }

    public function __jsonBlobIntoSource(Record $record, $jsonBlob)
    {
        $record->json_blob = json_encode($jsonBlob);
    }
}

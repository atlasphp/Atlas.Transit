<?php
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
    public function fromSourceToDomain(Record $record, array &$parameters) : void
    {
        $parameters['emailAddress'] = new Email($record->email_address);

        $parameters['address'] = new Address(
            $record->address->street,
            $record->address->city,
            $record->address->region,
            $record->address->postcode
        );

        $parameters['dateTimeGroup'] = new DateTimeWithZone(
            $record->date_time,
            new \DateTimeZone($record->time_zone)
        );

        $parameters['jsonBlob'] = json_decode($record->json_blob);
    }

    public function fromDomainToSource(array &$properties, Record $record) : void
    {
        $record->email_address = $properties['emailAddress']->get();
        unset($properties['emailAddress']);

        // now, what if the Domain object is new? Then the $record won't
        // have a related address record yet. this means either a special
        // check-and-create here, or a Mapper Relationship type that always
        // creates a Record object? A la oneToOne->always() manyToOne->always().
        // the problem with always() is that it means you need to descend into
        // *those* relateds to find always() as well. (or ->required().)
        $record->address->street = $properties['address']->getStreet();
        $record->address->city = $properties['address']->getCity();
        $record->address->region = $properties['address']->getState();
        $record->address->postcode = $properties['address']->getZip();
        unset($properties['address']);

        $record->date_time = $properties['dateTimeGroup']->getDateTime();
        $record->time_zone = $properties['dateTimeGroup']->getZone();
        unset($properties['dateTimeGroup']);

        $record->json_blob = json_encode($properties['jsonBlob']);
        unset($properties['jsonBlob']);
    }
}

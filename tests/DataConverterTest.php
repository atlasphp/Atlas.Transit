<?php
declare(strict_types=1);

namespace Atlas\Transit;

use Atlas\Mapper\Related;
use Atlas\Orm\Atlas;
use Atlas\Transit\DataSource\Fake\FakeRecord;
use Atlas\Transit\DataSource\Fake\FakeRow;
use Atlas\Transit\DataSource\FakeAddress\FakeAddressRecord;
use Atlas\Transit\DataSource\FakeAddress\FakeAddressRow;
use Atlas\Transit\Domain\Entity\Fake\Fake;
use Atlas\Transit\Domain\Value\Address;
use Atlas\Transit\Domain\Value\Bag;
use Atlas\Transit\Domain\Value\DateTime;
use Atlas\Transit\Domain\Value\Email;
use stdClass;

class DataConverterTest extends \PHPUnit\Framework\TestCase
{
    protected $transit;

    protected function setUp()
    {
        $this->transit = FakeTransit::new(
            Atlas::new('sqlite::memory:'),
            'Atlas\Transit\DataSource\\'
        );
    }

    public function test()
    {
        // fake a record from the database
        $fakeRecord = new FakeRecord(
            new FakeRow([
                'fake_id' => '1',
                'email_address' => 'fake@example.com',
                'date_time' => '1970-08-08',
                'json_blob' => json_encode(['foo' => 'bar', 'baz' => 'dib'])
            ]),
            new Related([
                'address' => new FakeAddressRecord(
                    new FakeAddressRow([
                        'fake_address_id' => '2',
                        'fake_id' => '1',
                        'street' => '123 Main',
                        'city' => 'Beverly Hills',
                        'region' => 'CA',
                        'postcode' => '90210'
                    ]),
                    new Related([])
                ),
            ])
        );

        // create an entity from the fake record as if it had been selected
        $fakeEntity = $this->transit
            ->getHandlerLocator()
            ->get(Fake::CLASS)
            ->newDomain(
                $fakeRecord
            );

        // make sure we have the value objects
        $this->assertInstanceOf(Email::CLASS, $fakeEntity->emailAddress);
        $this->assertInstanceOf(Address::CLASS, $fakeEntity->address);
        $this->assertInstanceOf(Bag::CLASS, $fakeEntity->jsonBlob);
        $this->assertInstanceOf(DateTime::CLASS, $fakeEntity->dateTime);

        // make sure their values are as expected
        $expect = [
            'emailAddress' => [
                'address' => 'fake@example.com',
            ],
            'address' => [
                'street' => '123 Main',
                'city' => 'Beverly Hills',
                'state' => 'CA',
                'zip' => '90210',
            ],
            'dateTime' => [
                'date' => '1970-08-08',
                'time' => '00:00:00',
            ],
            'jsonBlob' => [
                 'foo' => 'bar',
                 'baz' => 'dib',
            ],
            'fakeId' => 1,
        ];

        $actual = $fakeEntity->getArrayCopy();
        $this->assertEquals($expect, $actual);

        // make sure the value objects actually change ...
        $old = $fakeEntity->address;
        $fakeEntity->changeAddress(
            '456 Central',
            'Bel Air',
            '90007',
            'CA'
        );
        $this->assertNotSame($old, $fakeEntity->address);

        $old = $fakeEntity->emailAddress;
        $fakeEntity->changeEmailAddress('fake_changed@example.com');
        $this->assertNotSame($old, $fakeEntity->emailAddress);

        // ... but that they stay connected to the FakeRecord when persisted.
        $this->transit->store($fakeEntity);
        $this->transit->persist();

        $expect = array (
            'fake_id' => 1,
            'email_address' => 'fake_changed@example.com',
            'date_time' => '1970-08-08 00:00:00',
            'json_blob' => '{"foo":"bar","baz":"dib"}',
            'address' => array (
                'fake_address_id' => '2',
                'fake_id' => '1',
                'street' => '456 Central',
                'city' => 'Bel Air',
                'region' => '90007',
                'postcode' => 'CA',
            ),
        );
        $actual = $fakeRecord->getArrayCopy();
        $this->assertEquals($expect, $actual);
    }
}

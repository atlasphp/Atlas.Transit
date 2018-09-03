<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Entity\Fake;

use Atlas\Transit\Domain\Entity\Entity;
use Atlas\Transit\Domain\Value\Address;
use Atlas\Transit\Domain\Value\DateTimeWithZone;
use Atlas\Transit\Domain\Value\Email;
use stdClass;

class Fake extends Entity
{
    protected $emailAddress;
    protected $address;
    protected $dateTimeGroup;
    protected $jsonBlob;
    protected $fakeId;

    public function __construct(
        Email $emailAddress,
        Address $address,
        DateTimeWithZone $dateTimeGroup,
        stdClass $jsonBlob,
        int $fakeId = null
    ) {
        $this->emailAddress = $emailAddress;
        $this->address = $address;
        $this->dateTimeGroup = $dateTimeGroup;
        $this->jsonBlob = $jsonBlob;
        $this->fakeId = $fakeId;
    }

    public function changeEmailAddress(string $newEmailAddress)
    {
        $this->emailAddress = new Email($newEmailAddress);
    }

    public function changeAddress(
        string $newStreet,
        string $newCity,
        string $newState,
        string $newZip
    ) {
        $this->address = new Address(
            $newStreet,
            $newCity,
            $newState,
            $newZip
        );
    }

    public function changeTimeZone(string $newZone)
    {
        $this->dateTimeGroup = $this->dateTimeGroup->setTimeZone(
            new \DateTimeZone($newZone)
        );
    }
}

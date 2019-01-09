<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Entity\Fake;

use Atlas\Transit\Domain\Entity\Entity;
use Atlas\Transit\Domain\Value\Address;
use Atlas\Transit\Domain\Value\DateTime;
use Atlas\Transit\Domain\Value\Email;
use stdClass;

class Fake extends Entity
{
    protected $emailAddress;
    protected $address;
    protected $dateTime;
    protected $jsonBlob;
    protected $fakeId;

    public function __construct(
        Email $emailAddress,
        Address $address,
        DateTime $dateTime,
        stdClass $jsonBlob,
        int $fakeId = null
    ) {
        $this->emailAddress = $emailAddress;
        $this->address = $address;
        $this->dateTime = $dateTime;
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
        $this->dateTime = $this->dateTime->setTimeZone(
            new \DateTimeZone($newZone)
        );
    }
}

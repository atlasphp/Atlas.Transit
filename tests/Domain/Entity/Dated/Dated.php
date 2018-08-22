<?php
namespace Atlas\Transit\Domain\Entity\Dated;

use Atlas\Transit\Domain\Entity\Entity;
use DateTimeImmutable;

class Dated extends Entity
{
    protected $id;
    protected $name;
    protected $date;

    public function __construct(
        int $id,
        string $name,
        DateTimeImmutable $date
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->date = $date;
    }

    public function setDate($time, $timezone)
    {
        $this->date = new DateTimeImmutable($time, $timezone);
    }

    public function modifyDate($modify)
    {
        $this->date = $this->date->modify($modify);
        return $this->date;
    }

    public function getDate()
    {
        return $this->date;
    }
}

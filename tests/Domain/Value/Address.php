<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Value;

class Address extends Value
{
    protected $street;
    protected $city;
    protected $state;
    protected $zip;

    public function __construct(
        $street,
        $city,
        $state,
        $zip
    ) {
        parent::__construct();
        $this->street = $street;
        $this->city = $city;
        $this->state = $state;
        $this->zip = $zip;
    }

    public function change(
        $street,
        $city,
        $state,
        $zip
    ) {
        return $this->with([
            'street' => $street,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
        ]);
    }

    public function getStreet()
    {
        return $this->street;
    }

    public function getCity()
    {
        return $this->city;
    }

    public function getState()
    {
        return $this->state;
    }

    public function getZip()
    {
        return $this->zip;
    }

    private static function __transitFromSource(object $record, string $field)
    {
        return new static(
            $record->street,
            $record->city,
            $record->state,
            $record->zip
        );
    }

    private function __transitIntoSource(object $record, string $field)
    {
        $record->street = $this->street;
        $record->city = $this->city;
        $record->state = $this->state;
        $record->zip = $this->zip;
    }
}

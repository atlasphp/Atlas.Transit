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
        string $street,
        string $city,
        string $state,
        string $zip
    ) {
        parent::__construct();
        $this->street = $street;
        $this->city = $city;
        $this->state = $state;
        $this->zip = $zip;
    }

    public function change(
        string $street,
        string $city,
        string $state,
        string $zip
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
}

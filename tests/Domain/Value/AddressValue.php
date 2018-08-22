<?php
namespace Atlas\Transit\Domain\Value;

class AddressValue extends ValueObject
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
}

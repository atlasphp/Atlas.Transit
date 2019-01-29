<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Value;

/**
 * @Atlas\Transit\ValueObject
 */
class Email extends Value
{
    protected $address;

    public function __construct(string $address)
    {
        parent::__construct();
        $this->address = $address;
    }

    public function change(string $address)
    {
        return $this->with(['address' => $address]);
    }

    public function get()
    {
        return $this->address;
    }
}

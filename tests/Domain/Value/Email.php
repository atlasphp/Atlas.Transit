<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Value;

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

    private static function __transitFromSource(object $record, string $field)
    {
        return new static($record->$field);
    }

    private function __transitIntoSource(object $record, string $field)
    {
        $record->$field = $this->address;
    }
}

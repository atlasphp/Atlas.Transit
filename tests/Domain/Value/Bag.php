<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Value;

/**
 * @Atlas\Transit\ValueObject
 */
class Bag extends Value
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getArrayCopy() : array
    {
        return $this->data;
    }

    private static function __transitFromSource(object $record, string $field)
    {
        return new static(json_decode($record->$field, true));
    }

    private static function __transitIntoSource(self $domain, object $record, string $field)
    {
        $record->$field = json_encode($domain->data);
    }
}

<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Value;

class DateTimeWithZone extends DateTime
{
    public function get()
    {
        return $this->format('Y-m-d H:i:s e');
    }

    public function getDateTime()
    {
        return parent::get();
    }

    public function getZone()
    {
        return $this->format('e');
    }

    public function getArrayCopy()
    {
        return [
            'date' => $this->getDate(),
            'time' => $this->getTime(),
            'zone' => $this->getZone(),
        ];
    }

    private static function __transitFromSource(object $record, string $field)
    {
        return new static($record->$field);
    }

    private function __transitIntoSource(object $record, string $field)
    {
        $record->$field = $this->format('Y-m-d H:i:s e');
    }
}

<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Value;

use DateTimeImmutable;

/**
 * @Atlas\Transit\ValueObject
 */
class DateTime extends DateTimeImmutable
{
    public function get()
    {
        return $this->format('Y-m-d H:i:s');
    }

    public function getDate()
    {
        return $this->format('Y-m-d');
    }

    public function getTime()
    {
        return $this->format('H:i:s');
    }

    public function getArrayCopy()
    {
        return [
            'date' => $this->getDate(),
            'time' => $this->getTime(),
        ];
    }

    private static function __transitFromSource(object $record, string $field)
    {
        return new static($record->$field);
    }

    private function __transitIntoSource(object $record, string $field)
    {
        $record->$field = $this->format('Y-m-d H:i:s');
    }
}

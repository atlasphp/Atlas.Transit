<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Value;

use DateTimeImmutable;

/**
 * @Atlas\Transit\ValueObject
 * @Atlas\Transit\Factory self::transitFactory()
 * @Atlas\Transit\Updater self::transitUpdater()
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

    private static function transitFactory(object $record, string $field) : self
    {
        return new static($record->$field);
    }

    private static function transitUpdater(self $domain, object $record, string $field) : void
    {
        $record->$field = $domain->format('Y-m-d H:i:s');
    }
}

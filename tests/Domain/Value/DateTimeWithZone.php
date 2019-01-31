<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Value;

/**
 * @Atlas\Transit\ValueObject
 * @Atlas\Transit\Factory self::transitFactory()
 * @Atlas\Transit\Updater self::transitUpdater()
 */
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

    private static function transitFactory(object $record, string $field) : self
    {
        return new static($record->$field);
    }

    private static function transitUpdater(self $domain, object $record, string $field) : void
    {
        $record->$field = $domain->format('Y-m-d H:i:s e');
    }
}

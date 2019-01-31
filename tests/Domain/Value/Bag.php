<?php
declare(strict_types=1);

namespace Atlas\Transit\Domain\Value;

/**
 * @Atlas\Transit\ValueObject
 * @Atlas\Transit\Factory self::transitFactory()
 * @Atlas\Transit\Updater self::transitUpdater()
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

    private static function transitFactory(object $record, string $field) : self
    {
        return new static(json_decode($record->$field, true));
    }

    private static function transitUpdater(self $domain, object $record, string $field) : void
    {
        $record->$field = json_encode($domain->data);
    }
}

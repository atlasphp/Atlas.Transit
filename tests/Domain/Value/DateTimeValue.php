<?php
namespace Atlas\Transit\Domain\Value;

use DateTimeImmutable;

class DateTimeValue extends DateTimeImmutable
{
    public function get()
    {
        return $this->format('Y-m-d H:i:s');
    }
}

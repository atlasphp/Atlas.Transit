<?php
namespace Atlas\Transit\Domain\Value;

use DateTimeImmutable;

class DateValue extends DateTimeImmutable
{
    public function get()
    {
        return $this->format('Y-m-d');
    }
}

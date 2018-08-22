<?php
namespace Atlas\Transit\Domain\Value;

class DateTimeValue extends DateValue
{
    public function get()
    {
        return $this->format('Y-m-d H:i:s');
    }
}

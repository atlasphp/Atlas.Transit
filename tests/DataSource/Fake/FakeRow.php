<?php
namespace Atlas\Transit\DataSource\Fake;

use Atlas\Table\Row;

class FakeRow extends Row
{
    protected $cols = [
        'fake_id' => null,
        'email_address' => null,
        'date_time' => null,
        'time_zone' => null,
        'json_blob' => null,
    ];
}

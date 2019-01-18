<?php
declare(strict_types=1);

namespace Atlas\Transit\DataSource\Fake;

use Atlas\Table\Row;

class FakeRow extends Row
{
    protected $cols = [
        'fake_id' => null,
        'email_address' => null,
        'date_time' => null,
        'json_blob' => null,
        'address_street' => null,
        'address_city' => null,
        'address_state' => null,
        'address_zip' => null,
    ];
}
